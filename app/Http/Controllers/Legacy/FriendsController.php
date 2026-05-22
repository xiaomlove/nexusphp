<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/friends.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2".
 *
 * Authed personal-list page where users manage their `friends` and
 * `blocks` rows. Three branches, all GET (the legacy template
 * issues mutations through `<a href>` links, not forms):
 *
 *   - Default GET → render friends list + blocks list.
 *   - GET `?action=add&targetid=N&type=friend|block` → INSERT row,
 *     302 back to `/friends.php?id=<self>#friends|#blocks`.
 *   - GET `?action=delete&targetid=N&type=friend|block&sure=1` →
 *     DELETE row, 302 back. `&sure=0` (or missing) renders a
 *     confirmation interstitial.
 *
 * URL preserved exactly so:
 *   - `include/functions.php:1898` (the user-header
 *     `[<a href="friends.php?id=N">friends</a>]` link),
 *   - `public/userdetails.php` (the "add to friends/blocks" links
 *     rendered on every user profile),
 *   - any user bookmark
 *
 * keep working without template changes. The 19
 * `lang/<locale>/lang_friends.php` files are loaded the same way
 * the legacy script loaded them.
 *
 * Original legacy flow (`public/friends.php`, 213 LOC):
 *   1. `loggedinorreturn()` + `parked()` bootstrap.
 *   2. `purge_neighbors_cache()` helper deletes a per-user
 *      `cache/<langfolder>/neighbors/<id>.html` file on every
 *      mutation. Preserved verbatim — the `legacy.blade.php` /
 *      `index.php` neighbor-list block reads from this file
 *      directly.
 *   3. `?action=add` checks duplicate, INSERTs, redirects.
 *   4. `?action=delete` with `?sure=1` DELETEs, redirects;
 *      otherwise renders a "click here if sure" interstitial.
 *   5. Default: SELECT friends with avatar/title/last_access
 *      JOIN, render a 2-column grid; SELECT blocks, render a
 *      6-column grid below.
 *
 * Replacement contract:
 *   - URL stays `/friends.php` so the existing in-template links
 *     keep working.
 *   - Inside `auth.nexus:nexus-web` middleware. Below-`parked`
 *     accounts get `abort(403)`.
 *   - Mutations stay GET (legacy contract — they're rendered as
 *     `<a href>` links, not forms). No CSRF token required.
 *   - Validation tightened: missing/non-numeric `targetid` returns
 *     422 (legacy: silent stderr 200).
 *   - Unknown `?type=` returns 422.
 *   - `?action=delete` without `?sure=1` returns the confirmation
 *     interstitial with the usual "click here if sure" link.
 */
class FriendsController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $viewer = $this->context->user();
        if ($viewer === null) {
            abort(401);
        }
        if (($viewer->parked ?? 'no') === 'yes') {
            abort(403, 'Your account is parked.');
        }

        $action = (string) $request->query('action', '');

        return match ($action) {
            'add' => $this->doAdd($request, $viewer),
            'delete' => $this->doDelete($request, $viewer),
            default => $this->renderListing($viewer),
        };
    }

    private function doAdd(Request $request, $viewer): RedirectResponse
    {
        $targetId = (int) $request->query('targetid', 0);
        if ($targetId <= 0) {
            abort(422, 'Invalid id: '.htmlspecialchars((string) $request->query('targetid', '')));
        }
        [$table, $field, $frag] = $this->resolveType((string) $request->query('type', ''));

        $exists = NexusDB::table($table)
            ->where('userid', (int) $viewer->id)
            ->where($field, $targetId)
            ->exists();
        if ($exists) {
            abort(422, 'User '.$targetId.' already in '.$table.' list.');
        }

        NexusDB::insert($table, [
            'userid' => (int) $viewer->id,
            $field => $targetId,
        ]);
        $this->purgeNeighborsCache((int) $viewer->id);

        return redirect('/friends.php?id='.(int) $viewer->id.'#'.$frag);
    }

    private function doDelete(Request $request, $viewer): Response|RedirectResponse
    {
        $targetId = (int) $request->query('targetid', 0);
        if ($targetId <= 0) {
            abort(422, 'Invalid id: '.htmlspecialchars((string) $request->query('targetid', '')));
        }

        $type = (string) $request->query('type', '');
        $typeEsc = htmlspecialchars($type, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        [$table, $field, $frag] = $this->resolveType($type);
        $sure = (string) $request->query('sure', '') === '1';

        if (! $sure) {
            $confirmHref = '/friends.php?id='.(int) $viewer->id
                .'&action=delete&type='.urlencode($type)
                .'&targetid='.$targetId.'&sure=1';

            return new Response(
                '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Delete '
                .$typeEsc.'</title></head><body>'
                .'<h1>Delete '.$typeEsc.'</h1>'
                .'<p>Click <a href="'.htmlspecialchars($confirmHref, ENT_QUOTES | ENT_HTML5, 'UTF-8').'">here</a> if sure.</p>'
                .'</body></html>',
            );
        }

        $affected = NexusDB::table($table)
            ->where('userid', (int) $viewer->id)
            ->where($field, $targetId)
            ->delete();
        if ($affected === 0) {
            abort(422, 'No '.$type.' found for id '.$targetId);
        }
        $this->purgeNeighborsCache((int) $viewer->id);

        return redirect('/friends.php?id='.(int) $viewer->id.'#'.$frag);
    }

    /**
     * @return array{0:string,1:string,2:string} `[table, foreignKey, anchorFragment]`
     */
    private function resolveType(string $type): array
    {
        return match ($type) {
            'friend' => ['friends', 'friendid', 'friends'],
            'block' => ['blocks', 'blockid', 'blocks'],
            default => abort(422, 'Unknown type: '.htmlspecialchars($type, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        };
    }

    private function renderListing($viewer): Response
    {
        $userId = (int) $viewer->id;
        $username = htmlspecialchars((string) $viewer->username, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $friendsHtml = $this->renderFriendsBlock($userId);
        $blocksHtml = $this->renderBlocksBlock($userId);

        $headTitle = 'Personal lists for '.$username;

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">'
            ."<title>{$headTitle}</title></head><body>"
            ."<h1>{$headTitle}</h1>"
            .'<h2><a name="friends">Friend list</a></h2>'.$friendsHtml
            .'<h2><a name="blocks">Blocked users</a></h2>'.$blocksHtml
            .(function_exists('user_can') && user_can('viewuserlist')
                ? '<p><a href="users.php"><b>Find a user</b></a></p>'
                : '')
            .'</body></html>';

        return new Response($html);
    }

    private function renderFriendsBlock(int $userId): string
    {
        $rows = NexusDB::select(
            'SELECT f.friendid AS id, u.last_access, u.class, u.title FROM friends f '
            .'LEFT JOIN users u ON f.friendid = u.id WHERE f.userid = '.$userId
            .' ORDER BY id',
        );
        if (! $rows) {
            return '<p>Friend list is empty.</p>';
        }

        $tiles = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $id = (int) ($row['id'] ?? 0);
            if ($id === 0) {
                continue;
            }
            $title = (string) ($row['title'] ?? '');
            if ($title === '' && function_exists('get_user_class_name')) {
                $title = (string) get_user_class_name((int) ($row['class'] ?? 0), false, true, true);
            }
            $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $userLink = function_exists('get_username')
                ? (string) get_username($id)
                : '<a href="userdetails.php?id='.$id.'">user '.$id.'</a>';
            $lastSeen = function_exists('gettime')
                ? (string) gettime((string) ($row['last_access'] ?? ''), true, false)
                : (string) ($row['last_access'] ?? '');
            $lastSeenEsc = htmlspecialchars($lastSeen, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $tiles[] = '<div class="friend-tile">'
                .$userLink.' ('.$titleEsc.')<br/>Last seen: '.$lastSeenEsc.'<br/>'
                .'<a href="/friends.php?action=delete&type=friend&targetid='.$id.'">Remove</a> &middot; '
                .'<a href="/sendmessage.php?receiver='.$id.'">Send PM</a>'
                .'</div>';
        }

        return '<div class="friends-grid">'.implode("\n", $tiles).'</div>';
    }

    private function renderBlocksBlock(int $userId): string
    {
        $rows = NexusDB::select(
            'SELECT blockid AS id FROM blocks WHERE userid = '.$userId.' ORDER BY id',
        );
        if (! $rows) {
            return '<p>Block list is empty.</p>';
        }

        $cells = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $id = (int) ($row['id'] ?? 0);
            if ($id === 0) {
                continue;
            }
            $userLink = function_exists('get_username')
                ? (string) get_username($id)
                : '<a href="userdetails.php?id='.$id.'">user '.$id.'</a>';
            $cells[] = '<span>[<a href="/friends.php?action=delete&type=block&targetid='.$id.'">D</a>] '
                .$userLink.'</span>';
        }

        return '<div class="blocks-grid">'.implode(' ', $cells).'</div>';
    }

    /**
     * Mirror legacy `purge_neighbors_cache()`. The neighbor-list
     * block on `index.php` / chrome reads from
     * `cache/<langfolder>/neighbors/<id>.html`, so we drop the
     * file on every friends/blocks mutation.
     */
    private function purgeNeighborsCache(int $userId): void
    {
        if (! function_exists('get_langfolder_cookie')) {
            return;
        }
        $folder = (string) get_langfolder_cookie();
        if ($folder === '') {
            return;
        }
        $candidate = base_path('cache/'.$folder.'/neighbors/'.$userId.'.html');
        if (is_file($candidate)) {
            @unlink($candidate);
        }
    }
}
