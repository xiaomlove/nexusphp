<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Complain;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\ToolRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Nexus\Database\NexusDB;
use Nexus\Database\NexusLock;
use Throwable;

/**
 * Replacement for `public/complains.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`.
 *
 * The "Complains" feature is a small ticket-tracker for users
 * whose accounts have been disabled — they can submit a complaint
 * via email + body (no login required, since they cannot log in),
 * and staff respond. The complainant follows up via a UUID-based
 * "view this conversation" URL.
 *
 * Contract per action (preserved from the 234-LOC legacy script):
 *
 *   GET  ?action=compose (default)
 *     - Reachable to guests + disabled-but-stale-session users
 *       (`cur_user_check()` boots a stale `$CURUSER` from a removed
 *       row; we mirror that with a `$viewer === null` check).
 *     - Renders the compose form posting to `?action=new`.
 *     - 403 when `?action` is anything but `compose` and the viewer
 *       is logged in but not a staff member.
 *
 *   POST action=new
 *     - Image captcha (`check_code`).
 *     - IP-rate-limit (`NexusLock::lockOrFail('complains:lock:'.IP, 10)`)
 *       and email-rate-limit (`NexusLock::lockOrFail('complains:lock:'.EMAIL, 600)`).
 *     - Email must match a `users` row with `enabled = 'no'`.
 *     - INSERTs the row, busts `COMPLAINTS_COUNT_CACHE`, redirects to
 *       `?action=view&id=<uuid>`.
 *
 *   GET  ?action=view&id=<uuid>
 *     - 36-character UUID lookup, otherwise 403.
 *     - For guests, prepends a "you have not logged in, save this
 *       URL" notice (so disabled users keep the link in case they
 *       lose the redirect).
 *     - Staff (`user_can('staffmem')`) sees the IP, the bound user
 *       row link / [view ban log] / [search account], and the
 *       Answer/Unanswer toggle.
 *     - Renders any `complain_replies` rows newest-first.
 *     - When `complain.answered = 0`, also renders a quickreply
 *       form posting `action=reply`.
 *
 *   POST action=reply
 *     - Inserts a row into `complain_replies`. When the reply is
 *       authored by a logged-in user, also sends an email to
 *       `complains.email` notifying them.
 *     - 302 back to the referer (legacy parity).
 *
 *   GET  ?action=list  (staff-only)
 *     - Two frames: "pending" (answered=0) without pagination, and
 *       "processed" (answered=1) with `pager(20, ...)` style links.
 *
 *   POST action=answered / action=unanswered  (staff-only)
 *     - Toggles `complain.answered` and busts the same cache key,
 *       302 back to the referer.
 *
 * URL preserved exactly — `complains.php` is linked from
 * `public/login.php:111` (the legacy "complain about a banned
 * account" link on the login page) and `include/functions.php:2481`
 * (the `complains.php?action=list` reminder rendered for staff in
 * the page header). Both links keep working without template
 * changes.
 *
 * Output chrome: legacy `stdhead()` / `stdfoot()`. We follow the
 * same chrome-less manual `<html>` wrap precedent already used by
 * `BitBucketLogController`, `ClaimController`,
 * `ContactStaffController`, `RulesController`. Phase 5 will
 * replace the envelope with native Blade partials.
 *
 * Localisation: legacy `lang/<locale>/lang_complains.php` (19
 * locales) is loaded through `require_once
 * get_langfile_path('complains')`. The 19 dictionaries are NOT
 * deleted in this PR; porting them to Laravel translations is
 * deferred to Phase 5 (same precedent as `ViewPeerListController`).
 *
 * CSRF: the legacy form had no `@csrf` token; the route is
 * therefore listed in `App\Http\Middleware\VerifyCsrfToken::$except`.
 */
class ComplainsController extends Controller
{
    private const PROCESSED_PER_PAGE = 20;

    private const UUID_LENGTH = 36;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        // Bridge into the legacy lang dictionary. Done first so
        // every error path below already has $lang_complains
        // available.
        require_once get_langfile_path('complains');

        $viewer = $this->context->user();
        $isStaff = $viewer !== null && (bool) user_can('staffmem');

        // Page-level gate: a logged-in *non-staff* user cannot use
        // the page. Disabled users see the page even though they
        // have a stale session because `LegacyContext::user()`
        // returns null when `users.enabled != 'yes'`.
        if ($viewer !== null && ! $isStaff) {
            abort(403);
        }
        // Feature gate: only staff bypass the toggle. When complains
        // are disabled site-wide, non-staff get the localised
        // "complain not enabled" envelope.
        if (! $isStaff && ! Setting::getIsComplainEnabled()) {
            return $this->envelope(
                $this->lang('std_error', 'functions'),
                $this->lang('complain_not_enabled'),
            );
        }

        $viewerId = $viewer?->id ?? 0;

        if ($request->isMethod('POST')) {
            $action = (string) $request->input('action', '');

            return match ($action) {
                'new' => $this->handleNewComplain($request),
                'reply' => $this->handleReply($request, (int) $viewerId),
                'answered', 'unanswered' => $this->handleToggleAnswered($request, $isStaff, $action),
                default => $this->permissionDenied(),
            };
        }

        $action = (string) $request->query('action', '');

        return match ($action) {
            'list' => $this->handleList($request, $isStaff),
            'view' => $this->handleView($request, $isStaff),
            'compose', '' => $this->handleCompose($viewer),
            default => $this->permissionDenied(),
        };
    }

    private function handleNewComplain(Request $request): Response|RedirectResponse
    {
        if ($this->context->user() !== null) {
            // Mirror `cur_user_check()` for already-logged-in
            // requests: legacy used it as a defensive sanity check.
            // The controller-level gate (above) already 403s
            // non-staff sessions; staff that POST `?action=new`
            // would land here, which is an authoring loop the
            // legacy script tolerated but does not actually
            // support. Mirror the legacy behaviour: no early-return.
        }

        $imagehash = (string) $request->input('imagehash', '');
        $imagestring = (string) $request->input('imagestring', '');
        if (! check_code($imagehash, $imagestring, 'complains.php')) {
            return $this->envelope(
                $this->lang('std_error', 'functions'),
                $this->lang('text_new_failure'),
            );
        }

        try {
            NexusLock::lockOrFail('complains:lock:'.getip(), 10);
        } catch (Throwable $e) {
            return $this->envelope(
                $this->lang('std_error', 'functions'),
                $e->getMessage(),
            );
        }

        $email = filter_var((string) $request->input('email', ''), FILTER_VALIDATE_EMAIL);
        if ($email === false || $email === '') {
            return $this->envelope(
                $this->lang('std_error', 'functions'),
                $this->lang('text_new_failure'),
            );
        }

        try {
            NexusLock::lockOrFail('complains:lock:'.$email, 600);
        } catch (Throwable $e) {
            return $this->envelope(
                $this->lang('std_error', 'functions'),
                $e->getMessage(),
            );
        }

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return $this->envelope(
                $this->lang('std_error', 'functions'),
                $this->lang('text_new_failure'),
            );
        }

        // Email must match a disabled user. The legacy script kept
        // this check; we mirror it byte-for-byte since complaints
        // from random outside emails are spam.
        $user = User::query()
            ->where('email', $email)
            ->where('enabled', 'no')
            ->first(['id']);
        if ($user === null) {
            return $this->envelope(
                $this->lang('std_error', 'functions'),
                $this->lang('text_new_failure'),
            );
        }

        $newId = (int) NexusDB::table('complains')->insertGetId([
            'uuid' => NexusDB::raw('UUID()'),
            'email' => $email,
            'body' => htmlspecialchars($body),
            'added' => NexusDB::raw('NOW()'),
            'ip' => (string) getip(),
        ]);

        Cache::forget('COMPLAINTS_COUNT_CACHE');

        $newUuid = (string) NexusDB::table('complains')
            ->where('id', $newId)
            ->value('uuid');

        return redirect('/complains.php?action=view&id='.$newUuid);
    }

    private function handleReply(Request $request, int $viewerId): Response|RedirectResponse
    {
        $idRaw = $request->input('id');
        $id = $this->validatePositiveInt($idRaw);
        $body = trim((string) $request->input('body', ''));

        if ($id === null || $body === '') {
            return $this->envelope(
                $this->lang('std_error', 'functions'),
                $this->lang('text_new_failure'),
            );
        }

        $complain = Complain::query()->find($id);
        if ($complain === null) {
            return $this->envelope(
                $this->lang('std_error', 'functions'),
                $this->lang('text_new_failure'),
            );
        }

        NexusDB::table('complain_replies')->insert([
            'complain' => $id,
            'userid' => $viewerId,
            'added' => NexusDB::raw('NOW()'),
            'body' => htmlspecialchars($body),
            'ip' => (string) getip(),
        ]);

        // Email the complainant — but only when the reply is from a
        // logged-in user (i.e. staff). Mirror the legacy guard
        // (`if ($uid > 0)`).
        if ($viewerId > 0) {
            try {
                $toolRep = new ToolRepository;
                $url = getSchemeAndHttpHost().'/complains.php?action=view&id='.$complain->uuid;
                $toolRep->sendMail(
                    (string) $complain->email,
                    $this->lang('reply_notify_subject'),
                    sprintf(
                        $this->lang('reply_notify_body'),
                        get_setting('basic.SITENAME'),
                        $url,
                    ),
                );
            } catch (Throwable $exception) {
                do_log($exception->getMessage(), 'error');
            }
        }

        return redirect($this->refererOrFallback($request, '/complains.php?action=view&id='.$complain->uuid));
    }

    private function handleToggleAnswered(Request $request, bool $isStaff, string $action): Response|RedirectResponse
    {
        if (! $isStaff) {
            return $this->permissionDenied();
        }
        $id = $this->validatePositiveInt($request->input('id'));
        if ($id === null) {
            return $this->permissionDenied();
        }
        NexusDB::table('complains')
            ->where('id', $id)
            ->update(['answered' => $action === 'answered' ? 1 : 0]);

        Cache::forget('COMPLAINTS_COUNT_CACHE');

        $uuid = (string) NexusDB::table('complains')->where('id', $id)->value('uuid');
        $fallback = $uuid !== '' ? '/complains.php?action=view&id='.$uuid : '/complains.php?action=list';

        return redirect($this->refererOrFallback($request, $fallback));
    }

    private function handleList(Request $request, bool $isStaff): Response
    {
        if (! $isStaff) {
            return $this->permissionDenied();
        }

        $body = '';

        if (! $request->has('page')) {
            $pendingRows = NexusDB::table('complains')
                ->where('answered', 0)
                ->orderByDesc('id')
                ->select(['added', 'uuid', 'email'])
                ->get();

            $body .= '<h2>'.htmlspecialchars($this->lang('pending_complaints')).'</h2>';
            if (count($pendingRows) > 0) {
                $body .= $this->renderListingTable($pendingRows);
            } else {
                $body .= '<p>'.htmlspecialchars($this->lang('no_pending_complaints')).'</p>';
            }
        }

        $answeredCount = (int) NexusDB::table('complains')->where('answered', 1)->count();
        $page = max(1, (int) $request->query('page', 1));
        $totalPages = (int) max(1, ceil($answeredCount / self::PROCESSED_PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * self::PROCESSED_PER_PAGE;

        $processedRows = NexusDB::table('complains')
            ->where('answered', 1)
            ->orderByDesc('id')
            ->offset($offset)
            ->limit(self::PROCESSED_PER_PAGE)
            ->select(['added', 'uuid', 'email'])
            ->get();

        $body .= '<h2>'.htmlspecialchars($this->lang('complaints_processed')).'</h2>';
        if (count($processedRows) > 0) {
            $body .= $this->renderPager($answeredCount, $page, $totalPages);
            $body .= $this->renderListingTable($processedRows);
            $body .= $this->renderPager($answeredCount, $page, $totalPages);
        } else {
            $body .= '<p>'.htmlspecialchars($this->lang('no_complaints_have_been_processed')).'</p>';
        }

        return $this->envelope($this->lang('text_complain'), $body);
    }

    /**
     * @param  iterable<object|array<string,mixed>>  $rows
     */
    private function renderListingTable(iterable $rows): string
    {
        $thAt = htmlspecialchars($this->lang('th_complain_at'));
        $thAccount = htmlspecialchars($this->lang('th_complain_account'));
        $thView = htmlspecialchars($this->lang('th_action_view'));

        $html = '<table width="100%" border="1" cellspacing="0" cellpadding="5">'."\n"
            ."<tr><td class=\"colhead\">{$thAt}</td>"
            ."<td class=\"colhead\">{$thAccount}</td>"
            ."<td class=\"colhead\">{$thView}</td></tr>\n";
        foreach ($rows as $row) {
            $r = (array) $row;
            $when = (string) ($r['added'] ?? '');
            $email = htmlspecialchars((string) ($r['email'] ?? ''));
            $uuid = (string) ($r['uuid'] ?? '');
            $href = '/complains.php?action=view&id='.urlencode($uuid);
            $html .= '<tr>'
                .'<td class="rowfollow">'.htmlspecialchars($when).'</td>'
                .'<td class="rowfollow">'.$email.'</td>'
                .'<td class="rowfollow"><a href="'.$href.'" class="faqlink">'.$thView.'</a></td>'
                ."</tr>\n";
        }
        $html .= '</table>'."\n";

        return $html;
    }

    private function renderPager(int $total, int $page, int $totalPages): string
    {
        if ($total <= self::PROCESSED_PER_PAGE) {
            return '';
        }
        $links = '';
        $base = '/complains.php?action=list';
        if ($page > 1) {
            $links .= '<a href="'.$base.'&page='.($page - 1).'">&lt;&lt; Prev</a> ';
        }
        $links .= '<b>'.$page.' / '.$totalPages.'</b>';
        if ($page < $totalPages) {
            $links .= ' <a href="'.$base.'&page='.($page + 1).'">Next &gt;&gt;</a>';
        }

        return '<p align="center">'.$links.'</p>'."\n";
    }

    private function handleView(Request $request, bool $isStaff): Response
    {
        $uuid = (string) $request->query('id', '');
        if (strlen($uuid) !== self::UUID_LENGTH) {
            return $this->permissionDenied();
        }
        $complainRow = NexusDB::table('complains')
            ->where('uuid', $uuid)
            ->first();
        if ($complainRow === null) {
            return $this->permissionDenied();
        }
        $complain = (array) $complainRow;

        $body = '';

        $isLogin = $this->context->user() !== null;
        if (! $isLogin) {
            $body .= '<h2>'.htmlspecialchars($this->lang('text_created_title')).'</h2>'
                .'<p style="font-weight: bold; color: red">'
                .htmlspecialchars($this->lang('text_created_note'))
                .'</p>';
        }

        $body .= '<h2>'.htmlspecialchars($this->lang('text_new_body')).'</h2>';
        $body .= htmlspecialchars($this->lang('text_added')).'：'
            .htmlspecialchars((string) $complain['added']).'<br />'
            .htmlspecialchars($this->lang('text_new_email')).' '
            .htmlspecialchars((string) $complain['email']);

        if ($isStaff) {
            $user = User::query()
                ->where('email', $complain['email'])
                ->first(['id', 'username']);
            if ($user !== null) {
                $body .= ' [<a href="userdetails.php?id='.(int) $user->id
                    .'" class="faqlink" target="_blank">'
                    .htmlspecialchars((string) $user->username).'</a>]';
                $body .= ' [<a href="user-ban-log.php?q='
                    .urlencode((string) $user->username)
                    .'" class="faqlink" target="_blank">'
                    .htmlspecialchars($this->lang('text_view_band_log')).'</a>]';
            } else {
                $body .= ' [<a href="usersearch.php?em='
                    .urlencode((string) $complain['email'])
                    .'" class="faqlink" target="_blank">'
                    .htmlspecialchars($this->lang('text_search_account')).'</a>]';
            }
            $body .= '<br />IP: '.htmlspecialchars((string) ($complain['ip'] ?? ''));
        }
        // `format_comment` produces HTML; do NOT escape its output.
        $body .= '<hr />'.format_comment((string) $complain['body']);

        // Replies.
        $body .= '<h2>'.htmlspecialchars($this->lang('text_replies')).'</h2>';
        $replyRows = NexusDB::table('complain_replies')
            ->where('complain', (int) $complain['id'])
            ->orderByDesc('id')
            ->get();
        if (count($replyRows) > 0) {
            foreach ($replyRows as $reply) {
                $r = (array) $reply;
                $author = (int) ($r['userid'] ?? 0) > 0
                    ? get_plain_username((int) $r['userid'])
                    : $this->lang('text_complainer');
                $body .= '<b>'.htmlspecialchars((string) $author)
                    .' @ '.htmlspecialchars((string) $r['added']);
                if ($isStaff) {
                    $body .= ' ('.htmlspecialchars((string) ($r['ip'] ?? '')).')';
                }
                $body .= ': </b>';
                $body .= format_comment((string) $r['body']).'<hr />';
            }
        } else {
            $body .= '<p align="center">'.htmlspecialchars($this->lang('text_no_replies')).'</p>';
        }

        if ((int) $complain['answered'] === 1) {
            $body .= '<p align="center">'.htmlspecialchars($this->lang('text_closed')).'</p>';
        } else {
            $replyLabel = htmlspecialchars($this->lang('text_reply'));
            $body .= '<form id="reply" method="post" action="/complains.php" style="margin-top: 1em">'
                .'<input type="hidden" name="action" value="reply" />'
                .'<input type="hidden" name="id" value="'.(int) $complain['id'].'" />'
                .'<p><b>'.$replyLabel.'</b></p>'
                .'<textarea name="body" rows="6" cols="60"></textarea><br />'
                .'<input type="submit" value="'.$replyLabel.'" />'
                .'</form>';
        }

        if ($isStaff) {
            $toggleAction = (int) $complain['answered'] === 1 ? 'unanswered' : 'answered';
            $toggleLabel = htmlspecialchars($this->lang(
                (int) $complain['answered'] === 1 ? 'text_unanswer_it' : 'text_answer_it'
            ));
            $body .= '<form action="/complains.php" method="post" style="text-align: center; margin-top: 2em">'
                .'<input type="hidden" name="action" value="'.$toggleAction.'" />'
                .'<input type="hidden" name="id" value="'.(int) $complain['id'].'" />'
                .'<button>'.$toggleLabel.'</button>'
                .'</form>';
        }

        return $this->envelope($this->lang('text_complain'), $body);
    }

    private function handleCompose(?User $viewer): Response
    {
        $title = htmlspecialchars($this->lang('text_complain'));
        $newComplainHeader = htmlspecialchars($this->lang('text_new_complain'));
        $emailLabel = htmlspecialchars($this->lang('text_new_email'));
        $bodyLabel = htmlspecialchars($this->lang('text_new_body'));
        $bodyPlaceholder = htmlspecialchars($this->lang('text_new_body_placeholder'));
        $submitLabel = htmlspecialchars($this->lang('text_new_submit'));

        // Image captcha block — `show_image_code()` echoes its own
        // `<tr>` wrapper directly, so capture into a buffer.
        ob_start();
        try {
            show_image_code();
        } catch (Throwable $e) {
            do_log('show_image_code threw: '.$e->getMessage(), 'error');
        }
        $imageCaptcha = (string) ob_get_clean();

        $body = '<h2>'.$newComplainHeader.'</h2>'
            .'<form action="/complains.php" method="post">'
            .'<input type="hidden" name="action" value="new" />'
            .'<table border="0" cellpadding="5">'
            .'<tr><td class="rowhead">'.$emailLabel.'</td>'
            .'<td class="rowfollow" align="left">'
            .'<input type="email" name="email" autocomplete="email" /></td></tr>'
            .'<tr><td class="rowhead">'.$bodyLabel.'</td>'
            .'<td class="rowfollow" align="left">'
            .'<textarea name="body" placeholder="'.$bodyPlaceholder.'"></textarea>'
            .'</td></tr>'
            .$imageCaptcha
            .'<tr><td class="toolbox" colspan="2" align="center">'
            .'<input type="submit" value="'.$submitLabel.'" class="btn" /></td></tr>'
            .'</table>'
            .'</form>';

        return $this->envelope($title, $body);
    }

    /**
     * Same posture as the legacy `permissiondenied()` helper — 200
     * with a chrome envelope explaining the access denial. Used as
     * a "soft 403" the legacy code returned for POST verbs that
     * dropped through the action switch.
     */
    private function permissionDenied(): Response
    {
        $title = $this->lang('std_error', 'functions');
        $body = $this->lang('std_access_denied', 'functions');

        return $this->envelope($title, $body);
    }

    private function refererOrFallback(Request $request, string $fallback): string
    {
        $referer = $request->headers->get('referer');
        if (! is_string($referer) || $referer === '') {
            return $fallback;
        }
        // Only follow same-host referers.
        $host = $request->getSchemeAndHttpHost();
        if (! str_starts_with($referer, $host)) {
            return $fallback;
        }

        return $referer;
    }

    private function validatePositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $string = (string) $value;
        if ($string === '' || ! preg_match('/^\d+$/', $string)) {
            return null;
        }
        $int = (int) $string;

        return $int > 0 ? $int : null;
    }

    /**
     * Read a localised label from one of the legacy lang dictionaries
     * loaded into globals. Most of the keys live in
     * `$lang_complains`, but a few generic ones (`std_error`,
     * `std_access_denied`) live in `$lang_functions` and must be
     * disambiguated explicitly.
     */
    private function lang(string $key, string $dictionary = 'complains'): string
    {
        if ($dictionary === 'complains') {
            global $lang_complains;

            return (string) ($lang_complains[$key] ?? $key);
        }
        global $lang_functions;

        return (string) ($lang_functions[$key] ?? $key);
    }

    private function envelope(string $title, string $body): Response
    {
        $titleEsc = htmlspecialchars($title);
        $html = <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}
</body>
</html>
HTML;

        return new Response($html);
    }
}
