<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/report.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`.
 *
 * The legacy script is a universal "report this thing to staff"
 * endpoint. It serves both the GET render of the confirmation
 * form (one HTML body per target type, rendered through the
 * `stderr()` legacy chrome wrapper) and the POST that inserts
 * the row into the `reports` table. Seven target types are
 * supported: `user`, `torrent`, `post` (forum post), `comment`,
 * `offer`, `request`, `subtitle`.
 *
 * Original legacy flow (~234 LOC):
 *   1. `dbconn();` + `loggedinorreturn();` + `parked();` bootstrap.
 *   2. POST: pick the matching `take<type>` field + `reason` and
 *      INSERT into `reports`. Bust the staff dashboard cache keys
 *      `staff_report_count` / `staff_new_report_count`.
 *   3. GET: pick the matching `<type>` field, validate the target
 *      exists, and render a "Are you sure?" form posting back to
 *      `/report.php`. The form is rendered as the body of a
 *      legacy `stderr()` chrome envelope.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...` (legacy `loggedinorreturn()` parity).
 *   - Parked user → `abort(403)` (legacy `parked()` parity).
 *   - Self-report blocked: `?user=N` where N == viewer id → 200
 *     with the localised "cannot report oneself" message.
 *   - Staff-report blocked: `?user=N` where the target's class is
 *     at or above `users.class >= UC_MODERATOR` (the legacy
 *     `$staffmem_class` constant) → 200 with the localised
 *     "cannot report <class-name>" message.
 *   - Bad / missing target id → 200 with the localised
 *     "invalid <type> id" message.
 *   - Successful POST: INSERT the row, bust the two staff
 *     dashboard cache keys, and 200 with the
 *     "successfully reported" message. Idempotent: an addedby /
 *     reportid / type triple that already exists short-circuits to
 *     "already reported" without a second INSERT.
 *   - Failed POST (no reason provided, or no matching `take<type>`
 *     POST field) → 200 with the appropriate localised message.
 *
 * URL preserved exactly so:
 *   - `app/Livewire/TorrentDetail.php:282` (modern UI report link),
 *   - `app/Http/Controllers/Legacy/ViewSnatchesController.php:126`
 *     (the `<a href="report.php?user=N">` cell in the snatch list),
 *   - `tests/Feature/Livewire/TorrentDetailActionRowTest.php:61`
 *     (which pins the `/report.php?torrent=N` URL contract),
 *   - `public/details.php:295`, `public/forums.php:1056`,
 *     `public/offers.php:221`, `public/subtitles.php:375`,
 *     `public/userdetails.php:382`, `public/viewrequests.php:137`
 *     (the legacy "report this <thing>" deep links), and
 *   - `include/functions.php:3115` (the comment-report icon
 *     rendered inside the comment-row toolbar)
 *
 * keep working without template/JS changes.
 *
 * Output chrome: legacy `stderr()` rendered the body inside the
 * full `stdhead()` / `stdfoot()` envelope. We follow the same
 * chrome-less manual `<html>` wrap precedent already used by
 * `BitBucketLogController`, `ContactStaffController`,
 * `RulesController`, `ClaimController`. Phase 5 will replace the
 * envelope with native Blade partials.
 *
 * Localisation: the per-locale labels (column headers, "Are you
 * sure?", "Confirm", error messages) come from the legacy
 * `lang/<locale>/lang_report.php` dictionaries through
 * `require_once get_langfile_path('report')`. The 19 locale files
 * are NOT deleted in this PR; porting them to Laravel
 * translations is deferred to Phase 5 (same precedent as
 * `ViewPeerListController`).
 */
class ReportController extends Controller
{
    /**
     * Legacy moderator threshold — the floor at which a target
     * counts as "staff" and self-reports are blocked. Mirrors the
     * legacy `$staffmem_class` constant in `include/config.php`.
     */
    private const STAFFMEM_CLASS = User::CLASS_MODERATOR;

    /**
     * GET targets: query-string field name → reports.type enum
     * value. Order matters only for the legacy fallthrough
     * `elseif` chain in error messaging — multiple fields would
     * each trigger their own confirm form, but the controller
     * picks the first matching one.
     *
     * @var array<string,string>
     */
    private const GET_TARGETS = [
        'user' => 'user',
        'torrent' => 'torrent',
        'forumpost' => 'post',
        'commentid' => 'comment',
        'reportofferid' => 'offer',
        'reportrequestid' => 'request',
        'subtitle' => 'subtitle',
    ];

    /**
     * POST targets: form field name → reports.type enum value.
     *
     * @var array<string,string>
     */
    private const POST_TARGETS = [
        'takeuser' => 'user',
        'taketorrent' => 'torrent',
        'takeforumpost' => 'post',
        'takecommentid' => 'comment',
        'takereportofferid' => 'offer',
        'takerequestid' => 'request',
        'takesubtitleid' => 'subtitle',
    ];

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $viewer = $this->context->user();
        if ($viewer === null) {
            // The middleware redirects guests; this is belt-and-braces.
            abort(401);
        }
        if ((string) $viewer->parked === 'yes') {
            abort(403);
        }

        // Bridge into the legacy lang dictionary so per-locale
        // labels keep working for all 19 locales without porting
        // them to Laravel translations.
        require_once get_langfile_path('report');

        if ($request->isMethod('POST')) {
            return $this->handlePost($request, (int) $viewer->id);
        }

        return $this->handleGet($request, (int) $viewer->id);
    }

    private function handlePost(Request $request, int $viewerId): Response
    {
        $reason = trim((string) $request->input('reason', ''));

        foreach (self::POST_TARGETS as $field => $type) {
            $value = $request->input($field);
            if ($value === null || $value === '') {
                continue;
            }
            $reportId = $this->validateInt($value);
            if ($reportId === null) {
                return $this->message('std_error', 'std_invalid_'.$this->invalidIdLangSuffix($type));
            }
            if ($reason === '') {
                return $this->message('std_error', 'std_missing_reason');
            }

            return $this->insertReport($viewerId, $reportId, $type);
        }

        return $this->message('std_error', 'std_invalid_action');
    }

    private function handleGet(Request $request, int $viewerId): Response
    {
        foreach (self::GET_TARGETS as $field => $type) {
            $value = $request->query($field);
            if ($value === null || $value === '') {
                continue;
            }
            $targetId = $this->validateInt($value);
            if ($targetId === null) {
                return $this->message('std_error', 'std_invalid_'.$this->invalidIdLangSuffix($type));
            }

            return match ($type) {
                'user' => $this->confirmUser($targetId, $viewerId),
                'torrent' => $this->confirmTorrent($targetId),
                'post' => $this->confirmForumPost($targetId),
                'comment' => $this->confirmComment($targetId),
                'offer' => $this->confirmOffer($targetId),
                'request' => $this->confirmRequest($targetId),
                'subtitle' => $this->confirmSubtitle($targetId),
            };
        }

        return $this->message('std_error', 'std_invalid_action');
    }

    private function insertReport(int $viewerId, int $reportId, string $type): Response
    {
        $existing = NexusDB::table('reports')
            ->where('addedby', $viewerId)
            ->where('reportid', $reportId)
            ->where('type', $type)
            ->count();
        if ($existing > 0) {
            return $this->message('std_error', 'std_already_reported_this');
        }

        NexusDB::table('reports')->insert([
            'addedby' => $viewerId,
            'reportid' => $reportId,
            'type' => $type,
            'reason' => $this->reasonInput(),
            'added' => date('Y-m-d H:i:s'),
        ]);

        // Bust the staff-dashboard counters the legacy script
        // invalidated through `$Cache->delete_value`; the cache
        // adapter (`classes/class_cache_redis.php`) now delegates
        // to `Illuminate\Cache\Repository` so `Cache::forget` is
        // wire-equivalent to the legacy call.
        Cache::forget('staff_report_count');
        Cache::forget('staff_new_report_count');

        return $this->message('std_message', 'std_successfully_reported');
    }

    private function reasonInput(): string
    {
        return trim((string) request()->input('reason', ''));
    }

    /**
     * GET-target confirm renderers. Each one validates the target
     * exists and returns the matching localised "Are you sure?"
     * form. Form bodies are HTML built up by `sprintf` to keep the
     * exact wire shape the legacy script emitted — these strings
     * land inside a chrome-less envelope but the inner markup is
     * identical (per-locale labels included).
     */
    private function confirmUser(int $userId, int $viewerId): Response
    {
        if ($userId === $viewerId) {
            return $this->message('std_sorry', 'std_cannot_report_oneself');
        }
        $target = NexusDB::table('users')
            ->where('id', $userId)
            ->select(['username', 'class'])
            ->first();
        if ($target === null) {
            return $this->message('std_error', 'std_invalid_user_id');
        }
        $target = (array) $target;
        if ((int) $target['class'] >= self::STAFFMEM_CLASS) {
            $body = $this->lang('std_cannot_report')
                .get_user_class_name((int) $target['class'], false, true, true);

            return $this->envelope($this->lang('std_sorry'), $body);
        }

        $body = $this->lang('text_are_you_sure_user')
            .get_username(htmlspecialchars((string) $userId))
            .$this->lang('text_to_staff')
            .'<br />'
            .$this->lang('text_not_for_leechers')
            .'<br />'
            .$this->lang('text_reason_note')
            .'<br />'
            .$this->confirmForm('takeuser', (string) $userId);

        return $this->envelope($this->lang('std_are_you_sure'), $body);
    }

    private function confirmTorrent(int $torrentId): Response
    {
        $target = NexusDB::table('torrents')
            ->where('id', $torrentId)
            ->select(['name'])
            ->first();
        if ($target === null) {
            return $this->message('std_error', 'std_invalid_torrent_id');
        }
        $target = (array) $target;

        $body = $this->lang('text_are_you_sure_torrent')
            .'<a href=details.php?id='.htmlspecialchars((string) $torrentId).'><b>'
            .htmlspecialchars((string) $target['name']).'</b></a>'
            .$this->lang('text_to_staff')
            .'<br />'
            .$this->lang('text_reason_note')
            .'<br />'
            .$this->confirmForm('taketorrent', (string) $torrentId);

        return $this->envelope($this->lang('std_are_you_sure'), $body);
    }

    private function confirmForumPost(int $postId): Response
    {
        $target = NexusDB::table('topics')
            ->leftJoin('posts', 'posts.topicid', '=', 'topics.id')
            ->where('posts.id', $postId)
            ->select([
                'topics.id AS topicid',
                'topics.subject AS subject',
                'posts.userid AS postuserid',
            ])
            ->first();
        if ($target === null) {
            return $this->message('std_error', 'std_invalid_post_id');
        }
        $target = (array) $target;

        $postEsc = htmlspecialchars((string) $postId);
        $body = $this->lang('text_are_you_sure_post')
            .$postId
            .$this->lang('text_of_topic')
            .'<a href="forums.php?action=viewtopic&topicid='.(int) $target['topicid']
            .'&page=p'.$postEsc.'#'.$postEsc.'"><b>'
            .htmlspecialchars((string) $target['subject']).'</b></a>'
            .$this->lang('text_by')
            .get_username((int) $target['postuserid'])
            .$this->lang('text_to_staff')
            .'<br />'
            .$this->lang('text_reason_note')
            .'<br />'
            .$this->confirmForm('takeforumpost', (string) $postId);

        return $this->envelope($this->lang('std_are_you_sure'), $body);
    }

    private function confirmComment(int $commentId): Response
    {
        $target = NexusDB::table('comments')
            ->where('id', $commentId)
            ->select(['id', 'user', 'torrent', 'request', 'offer'])
            ->first();
        if ($target === null) {
            return $this->message('std_error', 'std_invalid_comment_id');
        }
        $target = (array) $target;

        if (! empty($target['torrent'])) {
            $name = (string) NexusDB::table('torrents')
                ->where('id', (int) $target['torrent'])
                ->value('name');
            $url = 'details.php?id='.(int) $target['torrent'].'#'.$commentId;
            $of = $this->lang('text_of_torrent');
        } elseif (! empty($target['offer'])) {
            $name = (string) NexusDB::table('offers')
                ->where('id', (int) $target['offer'])
                ->value('name');
            $url = 'offers.php?id='.(int) $target['offer'].'&off_details=1#'.$commentId;
            $of = $this->lang('text_of_offer');
        } else {
            // The legacy script preserved the orphaned-comment branch:
            // a comment row with neither torrent nor offer is dead data
            // (no parent), nothing to report against.
            return $this->message('std_error', 'std_orphaned_comment');
        }

        $body = $this->lang('text_are_you_sure_comment')
            .$commentId
            .$of
            .'<a href="'.$url.'"><b>'.htmlspecialchars($name).'</b></a>'
            .$this->lang('text_by')
            .get_username((int) $target['user'])
            .$this->lang('text_to_staff')
            .'<br />'
            .$this->lang('text_reason_note')
            .'<br />'
            .$this->confirmForm('takecommentid', (string) $commentId);

        return $this->envelope($this->lang('std_are_you_sure'), $body);
    }

    private function confirmOffer(int $offerId): Response
    {
        $target = NexusDB::table('offers')
            ->where('id', $offerId)
            ->select(['id', 'name'])
            ->first();
        if ($target === null) {
            return $this->message('std_error', 'std_invalid_offer_id');
        }
        $target = (array) $target;

        $body = $this->lang('text_are_you_sure_offer')
            .'<a href="offers.php?id='.(int) $target['id'].'&off_details=1"><b>'
            .htmlspecialchars((string) $target['name']).'</b></a>'
            .$this->lang('text_to_staff')
            .'<br />'
            .$this->lang('text_reason_note')
            .'<br />'
            .$this->confirmForm('takereportofferid', (string) $offerId);

        return $this->envelope($this->lang('std_are_you_sure'), $body);
    }

    private function confirmRequest(int $requestId): Response
    {
        $target = NexusDB::table('requests')
            ->where('id', $requestId)
            ->select(['id', 'request'])
            ->first();
        if ($target === null) {
            return $this->message('std_error', 'std_invalid_request_id');
        }
        $target = (array) $target;

        $body = $this->lang('text_are_you_sure_request')
            .'<a href="viewrequests.php?id='.(int) $target['id'].'&req_details=1"><b>'
            .htmlspecialchars((string) $target['request']).'</b></a>'
            .$this->lang('text_to_staff')
            .'<br />'
            .$this->lang('text_reason_note')
            .'<br />'
            .$this->confirmForm('takerequestid', (string) $requestId);

        return $this->envelope($this->lang('std_are_you_sure'), $body);
    }

    private function confirmSubtitle(int $subtitleId): Response
    {
        $target = NexusDB::table('subs')
            ->where('id', $subtitleId)
            ->select(['id', 'torrent_id', 'title'])
            ->first();
        if ($target === null) {
            return $this->message('std_error', 'std_invalid_subtitle_id');
        }
        $target = (array) $target;

        $body = $this->lang('text_are_you_sure_subtitle')
            .'<a href="downloadsubs.php?torrentid='.(int) $target['torrent_id']
            .'&subid='.(int) $target['id'].'"><b>'
            .htmlspecialchars((string) $target['title']).'</b></a>'
            .$this->lang('text_to_staff')
            .'<br />'
            .$this->lang('text_reason_note')
            .'<br />'
            .$this->confirmForm('takesubtitleid', (string) $subtitleId);

        return $this->envelope($this->lang('std_are_you_sure'), $body);
    }

    private function confirmForm(string $hiddenName, string $hiddenValue): string
    {
        return '<form method=post action=report.php>'
            .'<input type=hidden name="'.$hiddenName.'" value="'.htmlspecialchars($hiddenValue).'">'
            .$this->lang('text_reason_is')
            .'<input type=text style="width: 200px" name=reason>'
            .'<input type=submit value="'.$this->lang('submit_confirm').'">'
            .'</form>';
    }

    /**
     * Map a `reports.type` value to its `lang_report` "invalid id"
     * suffix. The legacy dictionary uses `std_invalid_<thing>_id`
     * keys with `<thing>` mostly matching the type, except `post`
     * stays as `post` (which is a forum post in the legacy
     * naming), so the table is explicit.
     */
    private function invalidIdLangSuffix(string $type): string
    {
        return match ($type) {
            'user', 'torrent', 'comment', 'offer', 'request', 'subtitle' => $type.'_id',
            'post' => 'post_id',
        };
    }

    private function validateInt(mixed $value): ?int
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

    private function lang(string $key): string
    {
        global $lang_report;

        return (string) ($lang_report[$key] ?? $key);
    }

    private function message(string $titleKey, string $bodyKey): Response
    {
        return $this->envelope($this->lang($titleKey), $this->lang($bodyKey));
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
<h2>{$titleEsc}</h2>
{$body}
</body>
</html>
HTML;

        return new Response($html);
    }
}
