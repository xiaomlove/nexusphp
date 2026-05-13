<?php

namespace App\Http\Controllers\Legacy;

use App\Events\TorrentPromotionChanged;
use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\TorrentState;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/freeleech.php` (deleted in the same PR).
 *
 * Phase 2 batch #7 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. `get_user_class() < UC_ADMINISTRATOR` → `stderr('Error',
 *      'Permission denied.')`.
 *   3. `$action = $_POST['action'] ?? $_GET['action'] ?? 'main'`.
 *   4. For each known action: `UPDATE torrents_state SET
 *      global_sp_state = N` (where N is the action-specific state),
 *      `$Cache->delete_value('global_promotion_state')`,
 *      `TorrentPromotionChanged::dispatch(0, N, true)`, then render
 *      an HTML success page via `stderr('Success', '...')`.
 *   5. `main` renders the menu of links.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_ADMINISTRATOR` →
 *     `abort(403)` (legacy `stderr()` returned HTTP 200 — tightened,
 *     same rationale as the rest of Phase 2).
 *   - GET (or any unknown `action`) → 200 chrome-less HTML "menu"
 *     page with one link per action (and a default `main` action).
 *   - POST/GET with a known `action` → applies the change, flushes
 *     the `global_promotion_state` cache via `TorrentState::flushCache()`
 *     (the canonical post-migration replacement for the legacy
 *     `$Cache->delete_value(...)`), dispatches
 *     `TorrentPromotionChanged`, and renders the success page.
 *
 * The `TorrentState::flushCache()` call replaces the legacy
 * `$Cache->delete_value('global_promotion_state')` — same cache key
 * (`Setting::TORRENT_GLOBAL_STATE_CACHE_KEY`), plus the additional
 * `publish_model_event('global_promotion_state_updated', 0)` that
 * `TorrentState` callers already rely on.
 */
class FreeleechController extends Controller
{
    /**
     * Action → [`global_sp_state`, success message] map. Keys match
     * the legacy `$_POST['action']` / `$_GET['action']` values so the
     * existing admin UI keeps working without changes.
     *
     * @var array<string, array{int, string}>
     */
    private const ACTIONS = [
        'setallfree' => [2, 'All torrents have been set free..'],
        'setall2up' => [3, 'All torrents have been set 2x up..'],
        'setall2up_free' => [4, 'All torrents have been set 2x up and free..'],
        'setallhalf_down' => [5, 'All torrents have been set half down..'],
        'setall2up_half_down' => [6, 'All torrents have been set half down..'],
        'setallnormal' => [1, 'All torrents have been set normal..'],
    ];

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_ADMINISTRATOR) {
            abort(403);
        }

        $action = (string) $request->input(
            'action',
            $request->query('action', 'main'),
        );

        if (isset(self::ACTIONS[$action])) {
            [$state, $message] = self::ACTIONS[$action];
            NexusDB::table('torrents_state')->update(['global_sp_state' => $state]);
            TorrentState::flushCache();
            TorrentPromotionChanged::dispatch(0, $state, true);

            return new Response($this->wrap('Success', '<p>'.htmlspecialchars($message).'</p>'));
        }

        return new Response($this->wrap('Select action', $this->renderMenu()));
    }

    private function renderMenu(): string
    {
        return '<ul>'."\n"
            .'<li>Click <a class="altlink" href="freeleech.php?action=setallfree">here</a> to set all torrents free..</li>'."\n"
            .'<li>Click <a class="altlink" href="freeleech.php?action=setall2up">here</a> to set all torrents 2x up..</li>'."\n"
            .'<li>Click <a class="altlink" href="freeleech.php?action=setall2up_free">here</a> to set all torrents 2x up and free..</li>'."\n"
            .'<li>Click <a class="altlink" href="freeleech.php?action=setallhalf_down">here</a> to set all torrents half down..</li>'."\n"
            .'<li>Click <a class="altlink" href="freeleech.php?action=setall2up_half_down">here</a> to set all torrents 2x up and half down..</li>'."\n"
            .'<li>Click <a class="altlink" href="freeleech.php?action=setallnormal">here</a> to set all torrents normal..</li>'."\n"
            .'</ul>'."\n";
    }

    private function wrap(string $title, string $body): string
    {
        $titleEsc = htmlspecialchars($title);

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
