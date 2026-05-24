<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\AttendanceRepository;
use App\Services\Captcha\Exceptions\CaptchaValidationException;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DateInterval;
use DatePeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/attendance.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2".
 *
 * Authed daily check-in page. Three observable shapes:
 *
 *   1. **GET, captcha enabled, not yet attended today** — render
 *      the check-in form with an inline image captcha. The user
 *      must POST with a valid captcha to claim the bonus.
 *   2. **POST, captcha enabled** — validate captcha → call
 *      `AttendanceRepository::attend()` → fall through to the
 *      success-calendar render.
 *   3. **GET / POST, captcha disabled, not yet attended today** —
 *      silent auto check-in (no form, no extra round trip), then
 *      render the success calendar.
 *
 * In all cases, when the user has already attended today, render
 * the success-calendar with their day count, points, attendance
 * cards remaining, today's ranking, and the FullCalendar event
 * stream that highlights past check-ins and offers retroactive
 * sign-in for missed days within the
 * `Attendance::MAX_RETROACTIVE_DAYS` window.
 *
 * URL preserved exactly so:
 *   - `include/functions.php:2253` (the `<a href="attendance.php">`
 *     link rendered in the user-header chrome on every legacy page),
 *   - the user-control-panel "you have N attendance cards" widget,
 *   - any user bookmarks
 *
 * keep working without template changes. The 19
 * `lang/<locale>/lang_attendance.php` files are kept and loaded via
 * `require` (mirrors the legacy `require get_langfile_path()` at
 * the top of the script) — translations matter for non-English
 * deployments and there is no Laravel-translation equivalent
 * to migrate to in this PR.
 *
 * The matching nginx exact-location entry lives in
 * `.docker/openresty/sites/app.conf.template`. POST is CSRF-exempt
 * (the legacy `<form method="post" action="attendance.php">` had
 * no `@csrf` field) — see
 * `App\Http\Middleware\VerifyCsrfToken::$except`.
 */
class AttendanceController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly AttendanceRepository $attendanceRepository,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if (($user->parked ?? 'no') === 'yes') {
            abort(403, 'Your account is parked.');
        }

        $lang = $this->loadLangAttendance();
        $captchaEnabled = $this->captchaEnabled();
        $userId = (int) $user->id;

        // POST: validate the captcha (when enabled), then attend.
        // The captcha validator throws a typed exception with a
        // localised message — surface it as a 422 to the client.
        if ($request->isMethod('POST')) {
            if ($captchaEnabled) {
                $this->verifyCaptcha($request);
            }
            $attendance = $this->attendanceRepository->attend($userId);
            if (! $attendance->is_updated) {
                abort(422, $lang['already_attended'] ?? 'Already attended today.');
            }
        } else {
            $attendance = $this->attendanceRepository->getAttendance($userId);
        }

        $today = Carbon::today();
        $hasAttendedToday = $attendance && $attendance->added && $attendance->added->isSameDay($today);

        // Captcha-disabled silent auto-attend: the legacy script
        // performed the same fall-through INSERT here, so a user
        // landing on the page with `captcha.attendance.enabled=no`
        // gets the bonus on first GET without ever seeing a form.
        if (! $captchaEnabled && ! $hasAttendedToday) {
            $attendance = $this->attendanceRepository->attend($userId);
            $hasAttendedToday = $attendance && $attendance->added && $attendance->added->isSameDay($today);
        }

        if (! $attendance) {
            $attendance = new Attendance([
                'uid' => $userId,
                'points' => 0,
                'days' => 0,
                'total_days' => 0,
            ]);
            $attendance->added = null;
            $hasAttendedToday = false;
        }

        if ($hasAttendedToday) {
            $body = $this->renderSuccess($user, $attendance, $today, $lang);
        } else {
            $body = $this->renderForm($lang, $captchaEnabled);
        }

        $title = htmlspecialchars($lang['title'] ?? 'Attendance', ENT_QUOTES | ENT_HTML5);

        $html = <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$title}</title>
<link rel="stylesheet" href="vendor/fullcalendar-5.10.2/main.min.css">
<script src="js/jquery.min.js"></script>
<script src="vendor/fullcalendar-5.10.2/main.min.js"></script>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    /**
     * Read `captcha.attendance.enabled` with the same string→bool
     * normalisation the legacy script did (matches `EditSetting`
     * Filament page so the toggle round-trips losslessly).
     */
    private function captchaEnabled(): bool
    {
        $iv = (string) get_setting('security.iv');
        if ($iv !== 'yes') {
            return false;
        }
        $setting = Setting::get('captcha.attendance.enabled', config('captcha.attendance.enabled', true));
        if (is_string($setting)) {
            return in_array(strtolower($setting), ['1', 'true', 'yes'], true);
        }

        return (bool) $setting;
    }

    /**
     * Validate the legacy `imagehash` / `imagestring` POST payload
     * through the configured captcha driver. Mirrors the legacy
     * `check_code()` helper, but converts the driver's
     * exception path into a 422 instead of `stderr()`-then-die.
     */
    private function verifyCaptcha(Request $request): void
    {
        $manager = captcha_manager();
        if (! $manager->isEnabled()) {
            return;
        }

        $payload = [
            'imagehash' => $request->input('imagehash'),
            'imagestring' => $request->input('imagestring'),
            'request' => $request->all(),
        ];
        $context = [
            'where' => 'attendance.php',
            'maxattemptlog' => false,
            'head' => true,
            'ip' => getip(),
        ];

        try {
            if ($manager->verify($payload, $context)) {
                return;
            }
        } catch (CaptchaValidationException $e) {
            $msg = $e->getMessage();
            abort(422, $msg !== '' ? $msg : 'Invalid captcha response.');
        }

        abort(422, 'Invalid captcha response.');
    }

    /**
     * Load the per-locale `lang_attendance.php` array. Mirrors the
     * legacy `require get_langfile_path()` call at the top of
     * `attendance.php`, but resolves the path explicitly so it
     * doesn't depend on `$_SERVER['SCRIPT_NAME']` being
     * `/attendance.php` (which is not the case under Laravel's
     * `nexus.php` front controller).
     *
     * @return array<string,string>
     */
    private function loadLangAttendance(): array
    {
        $path = base_path(get_langfile_path('attendance.php'));
        $lang_attendance = [];
        if (is_file($path)) {
            require $path;
        }

        return is_array($lang_attendance ?? null) ? $lang_attendance : [];
    }

    /**
     * Render the chrome-less check-in form. Mirrors the legacy
     * GET-without-attended branch — minimal markup, optional inline
     * image captcha, single submit button.
     *
     * @param  array<string,string>  $lang
     */
    private function renderForm(array $lang, bool $captchaEnabled): string
    {
        $title = htmlspecialchars($lang['title'] ?? 'Attendance');
        $buttonLabel = htmlspecialchars($lang['attend_button'] ?? 'Check in', ENT_QUOTES, 'UTF-8');

        $captchaRow = '';
        if ($captchaEnabled) {
            // `show_image_code()` echoes directly into the response,
            // so capture its output and splice it into the form.
            ob_start();
            show_image_code();
            $captchaRow = (string) ob_get_clean();
        }

        return '<h1 align="center">'.$title.'</h1>'."\n"
            .'<table width="100%" border="1" cellspacing="0" cellpadding="10"><tbody>'
            .'<tr><td class="text">'
            .'<div style="margin-top: 20px; text-align: center;">'
            .'<form method="post" action="/attendance.php" style="display: inline-block;">'
            .'<table border="0" cellpadding="5">'
            .$captchaRow
            .'<tr><td class="toolbox" colspan="2" align="center">'
            .'<input type="submit" value="'.$buttonLabel.'" class="btn">'
            .'</td></tr>'
            .'</table>'
            .'</form>'
            .'</div>'
            .'</td></tr>'
            .'</tbody></table>';
    }

    /**
     * Render the success calendar plus the bonus-rules side panel.
     *
     * @param  array<string,string>  $lang
     */
    private function renderSuccess(
        User $user,
        Attendance $attendance,
        Carbon $today,
        array $lang,
    ): string {
        $userId = (int) $user->id;
        $todayDate = $today->format('Y-m-d');

        $todayCounts = (int) AttendanceLog::query()->where('date', $todayDate)->count();
        $myLog = AttendanceLog::query()
            ->where('date', $todayDate)
            ->where('uid', $userId)
            ->first(['id']);
        $myRanking = 0;
        if ($myLog !== null) {
            $myRanking = (int) AttendanceLog::query()
                ->where('date', $todayDate)
                ->where('id', '<=', $myLog->id)
                ->count();
        }

        $count = (int) $attendance->total_days;
        $cdays = (int) $attendance->days;
        $points = (int) $attendance->points;
        $cards = (int) ($user->attendance_card ?? 0);

        $headerLeft = sprintf(
            ($lang['attend_info'] ?? 'You have signed in for %s days, %s of which were continuous, earning a total of %s points. Cards: %s.')
            .($lang['retroactive_description'] ?? ''),
            $count,
            $cdays,
            $points,
            $cards,
        );
        $headerRight = (string) nexus_trans('attendance.ranking', [
            'ranking' => $myRanking,
            'counts' => $todayCounts,
        ]);

        $events = $this->buildCalendarEvents($userId, $today, $lang);
        $eventsJson = json_encode($events, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $start = $today->clone()->subMonth(2);
        $end = $today->clone()->endOfMonth();
        $validRangeJson = json_encode([
            'start' => $start->format('Y-m-d'),
            'end' => $end->clone()->addDays(1)->format('Y-m-d'),
        ]);

        $confirmText = htmlspecialchars(
            $lang['retroactive_confirm_tip'] ?? 'Retroactively check in for ',
            ENT_QUOTES,
        );
        $localeJs = $this->localeJs();

        $rules = $this->renderBonusRules($lang);

        return '<h1 align="center">'.htmlspecialchars($lang['success'] ?? 'Attendance').'</h1>'
            .'<p>'.$headerLeft.'<span style="float:right">'.$headerRight.'</span></p>'
            .'<div style="display: flex;justify-content: center;padding: 20px 0">'
            .'<div id="calendar" style="width: 60%"></div>'
            .'</div>'
            .$rules
            ."<script>\n"
            ."let events = JSON.parse('".addslashes((string) $eventsJson)."');\n"
            ."let validRange = JSON.parse('".addslashes((string) $validRangeJson)."');\n"
            ."let confirmText = \"{$confirmText}\";\n"
            ."document.addEventListener('DOMContentLoaded', function() {\n"
            ."  var calendarEl = document.getElementById('calendar');\n"
            ."  var calendar = new FullCalendar.Calendar(calendarEl, {\n"
            ."    initialView: 'dayGridMonth',\n"
            ."    locale: '{$localeJs}',\n"
            ."    events: events,\n"
            ."    validRange: validRange,\n"
            ."    eventClick: function(info) {\n"
            ."      if (info.event.groupId == 'to_do') { retroactive(info.event.startStr); }\n"
            ."    }\n"
            ."  });\n"
            ."  calendar.render();\n"
            ."});\n"
            ."function retroactive(dateStr) {\n"
            ."  if (!window.confirm(confirmText + dateStr + ' ?')) return;\n"
            ."  jQuery.post('/misc/attendance-retroactive', {params: {date: dateStr}}, function (response) {\n"
            ."    if (response.ret != 0) { alert(response.msg); } else { location.reload(); }\n"
            ."  }, 'json');\n"
            ."}\n"
            .'</script>';
    }

    /**
     * Build the FullCalendar event stream for the past two months
     * up to and including `$today`. Mirrors the legacy
     * `DatePeriod` / `Carbon\CarbonPeriod` walk verbatim:
     *   - days the user signed in render as a background event
     *     (`display: 'background'`),
     *   - days with positive `points` add a numeric badge,
     *   - retroactive check-ins add the `retroactive_event_text`
     *     marker,
     *   - days within the `MAX_RETROACTIVE_DAYS` window that the
     *     user did NOT sign in for render as a `to_do` list-item
     *     (clicking it triggers the `attendanceRetroactive` AJAX
     *     POST).
     *
     * @param  array<string,string>  $lang
     * @return array<int,array<string,mixed>>
     */
    private function buildCalendarEvents(int $userId, Carbon $today, array $lang): array
    {
        $start = $today->clone()->subMonth(2);
        $end = $today->clone()->endOfMonth();
        $tomorrow = $today->clone()->addDay();

        $logs = AttendanceLog::query()
            ->where('uid', $userId)
            ->where('date', '>=', $start->format('Y-m-d'))
            ->get()
            ->keyBy('date');

        $interval = new DateInterval('P1D');
        $period = CarbonPeriod::make(new DatePeriod($start, $interval, $end));
        unset($interval); // suppress unused-var noise from static analysis

        $events = [];
        foreach ($period as $value) {
            if ($value->gte($tomorrow)) {
                continue;
            }
            $checkDate = $value->format('Y-m-d');
            $base = ['start' => $checkDate, 'end' => $checkDate];

            if ($logs->has($checkDate)) {
                $log = $logs->get($checkDate);
                $events[] = array_merge($base, ['display' => 'background']);
                if ((int) $log->points > 0) {
                    $events[] = array_merge($base, ['title' => (int) $log->points]);
                }
                if ($log->is_retroactive) {
                    $events[] = array_merge($base, [
                        'title' => $lang['retroactive_event_text'] ?? 'Retroactive',
                        'display' => 'list-item',
                    ]);
                }
            } elseif (
                $value->lte($today)
                && $value->diffInDays($today, true) <= Attendance::MAX_RETROACTIVE_DAYS
            ) {
                $events[] = array_merge($base, [
                    'groupId' => 'to_do',
                    'display' => 'list-item',
                ]);
            }
        }

        return $events;
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function renderBonusRules(array $lang): string
    {
        $initial = (int) get_setting('bonus.attendance_initial', Attendance::INITIAL_BONUS);
        $step = (int) get_setting('bonus.attendance_step', Attendance::STEP_BONUS);
        $max = (int) get_setting('bonus.attendance_max', Attendance::MAX_BONUS);
        $continuous = get_setting('bonus.attendance_continuous', Attendance::CONTINUOUS_BONUS);
        if (! is_array($continuous)) {
            $continuous = Attendance::CONTINUOUS_BONUS;
        }

        $items = '';
        $items .= '<li>'.sprintf($lang['initial'] ?? 'Initial bonus: %s.', $initial).'</li>';
        $items .= '<li>'.sprintf($lang['steps'] ?? 'Daily increment: %s, capped at %s.', $step, $max).'</li>';

        $continuousItems = '';
        foreach ($continuous as $day => $value) {
            $continuousItems .= '<li>'.sprintf(
                $lang['continuous'] ?? 'Continuous %s days: +%s.',
                (int) $day,
                (int) $value,
            ).'</li>';
        }
        if ($continuousItems !== '') {
            $items .= '<li><ol>'.$continuousItems.'</ol></li>';
        }

        return '<ul>'.$items.'</ul>';
    }

    /**
     * Pick the FullCalendar locale-pack name from the user's
     * `c_lang_folder` cookie. The legacy script hard-coded a tiny
     * `[en, chs, cht]` map and fell back to `en-us` for everyone
     * else; preserved verbatim.
     */
    private function localeJs(): string
    {
        $folder = get_langfolder_cookie();
        $map = [
            'en' => 'en-us',
            'chs' => 'zh-cn',
            'cht' => 'zh-tw',
        ];

        return $map[$folder] ?? 'en-us';
    }
}
