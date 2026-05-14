<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Resolves `/forum/post/{post}` to the canonical
 * `/forum/{forumid}/topic/{topicid}` URL by looking up the post's
 * topic and the topic's forum. Used by the Strangler Fig redirects
 * for `/forums.php?action=quotepost&postid=N` and
 * `/forums.php?action=editpost&postid=N`, which carry only a post id
 * in the URL but need both `forumid` and `topicid` to land on
 * `TopicView`.
 *
 * Like {@see ForumTopicRedirectController}, we deliberately do not run
 * a DB query before Laravel boots — the legacy entry point would have
 * to parse `.env` / load `dbconn.php` to learn the credentials, which
 * defeats the fast-path goal of the redirect. The two-hop redirect is
 * acceptable because the legacy URL is hit only by bookmarks / RSS /
 * cross-site links; first-party navigation already uses the canonical
 * URL directly.
 *
 * Behaviour:
 *
 *   - Post missing or its topic deleted → 404 (matches the legacy
 *     "post not found" / "topic not found" error surface).
 *   - Post found → 302 to `/forum/{forumid}/topic/{topicid}` with the
 *     query string preserved. `TopicView::mount()` performs the
 *     `forumid` / `minclassread` validation and (when `?compose=quote`
 *     or `?compose=edit` is present) reshapes the URL into a
 *     `?quote=N#reply` or `?edit=N#post-N` form so the page mounts
 *     with the inline ReplyForm pre-filled with the quote or with the
 *     inline EditPostForm already open on the target post.
 */
class ForumPostRedirectController extends Controller
{
    public function __invoke(Request $request, int $post): RedirectResponse
    {
        $row = Post::query()->select(['id', 'topicid'])->find($post);
        if ($row === null) {
            abort(404);
        }

        $topicRow = Topic::query()->select(['id', 'forumid'])->find((int) $row->topicid);
        if ($topicRow === null) {
            abort(404);
        }

        $forumId = (int) $topicRow->forumid;
        $topicId = (int) $topicRow->id;
        $postId = (int) $row->id;

        $query = $request->query();
        if (! is_array($query)) {
            $query = [];
        }

        $compose = $query['compose'] ?? null;
        unset($query['compose']);

        $fragment = '';
        if ($compose === 'quote') {
            $query['quote'] = (string) $postId;
            $fragment = '#reply';
        } elseif ($compose === 'edit') {
            $query['edit'] = (string) $postId;
            $fragment = '#post-'.$postId;
        }

        $qs = http_build_query($query);

        $location = '/forum/'.$forumId.'/topic/'.$topicId
            .($qs !== '' ? '?'.$qs : '')
            .$fragment;

        return redirect($location, 302);
    }
}
