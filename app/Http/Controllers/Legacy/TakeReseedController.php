<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/takereseed.php` (deleted in the same PR).
 *
 * Phase 2 batch #11 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow (42 LOC):
 *   1. `dbconn();` + `loggedinorreturn();` + `user_can('askreseed', true)`.
 *   2. `$reseedid = intval($_GET["reseedid"] ?? 0);`
 *   3. `SELECT seeders, last_reseed FROM torrents WHERE id = ?`.
 *   4. `SELECT COUNT(*) FROM peers WHERE torrent = ?` → bail via
 *      `stderr()` if > 0 ("torrent not dead").
 *   5. `strtotime(last_reseed) > TIMENOW - 900` → bail via `stderr()`
 *      ("reseed request sent recently"; 15-minute cooldown).
 *   6. `SELECT snatched.userid, ... FROM snatched JOIN users JOIN
 *      torrents WHERE snatched.finished='Yes' AND torrentid = ?`.
 *   7. For each finished snatcher, `Message::add(['sender' => 0,
 *      'receiver' => $row['userid'], 'subject' => msg_reseed_request,
 *      'msg' => msg_reseed_user ... ])`.
 *   8. `UPDATE torrents SET last_reseed = NOW(), seeders = $seederCount
 *      WHERE id = ?`.
 *   9. Render success HTML via `stdhead()` / `stdfoot()`.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...` (same URL the legacy
 *     `loggedinorreturn()` produced).
 *   - Below the `askreseed` permission threshold (default
 *     `User::CLASS_POWER_USER`, configurable via `$AUTHORITY['askreseed']`)
 *     → 403. Legacy script called `user_can('askreseed', true)`
 *     which `stderr()`'d at HTTP 200; tightened in line with the
 *     rest of Phase 2 (see `AllAgentsController` /
 *     `DonorlistController` for the same pattern).
 *   - Missing / non-positive `reseedid` → 422.
 *   - `torrents` row not found → 404.
 *   - Live peers (`peers.torrent = $id` count > 0) → 200 chrome-less
 *     HTML with the "torrent is not dead" notice. The legacy
 *     `stderr()` shape is preserved as a body string so a
 *     screen-scraping caller in the wild keeps seeing the same
 *     phrase.
 *   - Last reseed within 900s → 200 chrome-less HTML with the
 *     "request sent recently" notice (cooldown).
 *   - Happy path → fan out PMs to every finished snatcher, stamp
 *     `torrents.last_reseed = NOW()`, return 200 chrome-less HTML
 *     with the "it worked" notice. The whole fan-out runs inside a
 *     `NexusDB::transaction()` so a crash mid-loop cannot leave
 *     `torrents.last_reseed` updated while some PMs are missing
 *     (or vice versa).
 *
 * The cooldown is configurable via the legacy
 * `$AUTHORITY['askreseed']` setting only insofar as it gates who can
 * call the page; the 15-minute window is the legacy literal `900`
 * seconds and matches the wording of the
 * `lang_takereseed.std_reseed_sent_recently` translation.
 *
 * The PM body interpolates `details.php?id={id}` (a torrent details
 * deep link) without going through `nexus_trans()` — the controller
 * does not depend on the legacy `$lang_takereseed` global. Phrasing
 * is preserved verbatim from the canonical `lang/en/lang_takereseed.php`.
 */
class TakeReseedController extends Controller
{
    /** Cooldown between reseed requests for a given torrent, in seconds. */
    private const COOLDOWN_SECONDS = 900;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }

        // `user_can('askreseed', false, $uid)` bridges to the legacy
        // permission helper — the same helper that gated the click
        // target in `public/details.php:190`. Resolving here keeps
        // the threshold a single source of truth (the legacy
        // `$AUTHORITY['askreseed']` knob in `config/allconfig.php`),
        // matching the precedent set by `DelAcctAdminController`.
        if (! user_can('askreseed', false, (int) $user->id)) {
            abort(403);
        }

        $reseedId = (int) $request->query('reseedid', 0);
        if ($reseedId <= 0) {
            return new Response($this->wrap(
                'Reseed Request',
                $this->notice('Error', 'Invalid reseed id.'),
            ), 422);
        }

        $torrent = NexusDB::table('torrents')
            ->where('id', $reseedId)
            ->select(['id', 'name', 'seeders', 'last_reseed'])
            ->first();
        if ($torrent === null) {
            return new Response($this->wrap(
                'Reseed Request',
                $this->notice('Error', 'Invalid torrent.'),
            ), 404);
        }
        $torrentArr = (array) $torrent;

        $seederCount = (int) NexusDB::table('peers')
            ->where('torrent', $reseedId)
            ->count();
        if ($seederCount > 0) {
            return new Response($this->wrap(
                'Reseed Request',
                $this->notice('Error', 'Reseed request is not allowed if the torrent is not really dead!'),
            ));
        }

        $lastReseed = (string) ($torrentArr['last_reseed'] ?? '');
        if ($this->cooldownActive($lastReseed)) {
            return new Response($this->wrap(
                'Reseed Request',
                $this->notice('Error', 'A reseed request was sent recently. Please wait!'),
            ));
        }

        $rewarderName = (string) ($user->username ?? '');
        $torrentName = (string) ($torrentArr['name'] ?? '');

        // Pull every finished snatcher once, outside the transaction
        // — the snatch list is read-only here and keeping it out of
        // the transaction's row-locking footprint avoids contention
        // with the BitTorrent tracker writing fresh `snatched` rows.
        $snatchers = NexusDB::table('snatched')
            ->where('finished', 'yes')
            ->where('torrentid', $reseedId)
            ->pluck('userid');

        NexusDB::transaction(function () use ($snatchers, $rewarderName, $torrentName, $reseedId, $seederCount): void {
            $now = Carbon::now()->toDateTimeString();
            foreach ($snatchers as $userId) {
                $userId = (int) $userId;
                if ($userId <= 0) {
                    continue;
                }
                $body = $this->renderMessage($rewarderName, $torrentName, $reseedId);
                Message::add([
                    'sender' => 0,
                    'receiver' => $userId,
                    'subject' => 'Reseed Request',
                    'msg' => $body,
                    'added' => $now,
                ]);
            }

            NexusDB::table('torrents')
                ->where('id', $reseedId)
                ->update([
                    'last_reseed' => $now,
                    'seeders' => $seederCount,
                ]);
        });

        return new Response($this->wrap(
            'Reseed Request',
            $this->notice(null, 'It worked! Reseed request was sent.'),
        ));
    }

    /**
     * True when `$lastReseed` is non-empty and within the cooldown
     * window. The legacy script used `strtotime($row['last_reseed'])
     * > TIMENOW - 900`, which compares the unix-ts of the timestamp
     * directly; we use Carbon for the same comparison.
     */
    private function cooldownActive(string $lastReseed): bool
    {
        if ($lastReseed === '' || $lastReseed === '0000-00-00 00:00:00') {
            return false;
        }
        try {
            $lastTs = Carbon::parse($lastReseed)->timestamp;
        } catch (\Throwable) {
            return false;
        }

        return $lastTs > (Carbon::now()->timestamp - self::COOLDOWN_SECONDS);
    }

    /**
     * Build the PM body sent to every finished snatcher. Mirrors the
     * legacy concatenation:
     *
     *   "{msg_reseed_user}{username}{msg_ask_reseed}[url={protocol}{baseurl}/details.php?id={id}]{torrent_name}[/url]{msg_thank_you}"
     */
    private function renderMessage(string $rewarderName, string $torrentName, int $torrentId): string
    {
        $detailsUrl = '/details.php?id='.$torrentId;

        return 'Dear user, '
            .$rewarderName
            .' would like to ask you to reseed '
            ."[url={$detailsUrl}]"
            .$torrentName
            .'[/url]. '
            .'Thank you!';
    }

    private function notice(?string $title, string $message): string
    {
        $titleBlock = '';
        if ($title !== null && $title !== '') {
            $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $titleBlock = '<h1 align="center">'.$titleEsc.'</h1>'."\n";
        }
        $messageEsc = htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $titleBlock.'<p align="center">'.$messageEsc.'</p>'."\n";
    }

    private function wrap(string $title, string $body): string
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}</body>
</html>
HTML;
    }
}
