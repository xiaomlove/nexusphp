<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Repositories\SearchRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/bookmark.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. Original legacy flow:
 *
 *     header('Content-Type: text/xml; charset=utf-8');
 *     // ...plus cache-defeat headers...
 *     $torrentid = intval($_GET['torrentid'] ?? 0);
 *     if (isset($CURUSER)) {
 *         $existing = NexusDB::table('bookmarks')
 *             ->where('torrentid', $torrentid)->where('userid', $userid)->first();
 *         if ($existing) {
 *             $searchRep->deleteBookmark($existing['id']);
 *             NexusDB::table('bookmarks')->where(...)->delete();
 *             $Cache->delete_value('user_'.$userid.'_bookmark_array');
 *             echo "deleted";
 *         } else {
 *             $newId = NexusDB::insert('bookmarks', [...]);
 *             $Cache->delete_value('user_'.$userid.'_bookmark_array');
 *             $searchRep->addBookmark($newId);
 *             echo "added";
 *         }
 *     } else {
 *         echo "failed";
 *     }
 *
 * Toggling endpoint: any GET with `torrentid` flips that bookmark's
 * existence for the current user; the response is a plain-text token
 * the front-end JS uses to swap the icon ("added" / "deleted" /
 * "failed").
 *
 * The migrated controller preserves the contract exactly. We keep
 * the same `text/xml` Content-Type (the JS doesn't read it, but
 * changing it could break user scripts / proxies that depend on
 * the legacy mime).
 *
 * No auth middleware on the route — `failed` is a meaningful guest
 * response (the front-end JS pops a "log in to bookmark" toast).
 */
class BookmarkController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly SearchRepository $searchRepository,
    ) {}

    public function __invoke(Request $request): Response
    {
        $headers = [
            'Content-Type' => 'text/xml; charset=utf-8',
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').'GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ];

        $user = $this->context->user();
        if ($user === null) {
            return new Response('failed', 200, $headers);
        }

        $torrentId = (int) $request->query('torrentid', 0);
        $userId = (int) $user->id;

        $existing = NexusDB::table('bookmarks')
            ->where('torrentid', $torrentId)
            ->where('userid', $userId)
            ->first();

        if ($existing !== null) {
            $existing = (array) $existing;
            $this->searchRepository->deleteBookmark((int) $existing['id']);
            NexusDB::table('bookmarks')
                ->where('torrentid', $torrentId)
                ->where('userid', $userId)
                ->delete();
            NexusDB::cache_del('user_'.$userId.'_bookmark_array');

            return new Response('deleted', 200, $headers);
        }

        $newId = (int) NexusDB::insert('bookmarks', [
            'torrentid' => $torrentId,
            'userid' => $userId,
        ]);
        NexusDB::cache_del('user_'.$userId.'_bookmark_array');
        $this->searchRepository->addBookmark($newId);

        return new Response('added', 200, $headers);
    }
}
