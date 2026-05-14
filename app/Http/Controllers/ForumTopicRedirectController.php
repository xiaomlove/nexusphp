<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Resolves `/forum/topic/{topic}` to the canonical
 * `/forum/{forumid}/topic/{topic}` URL by looking up the topic's
 * forumid. Used by the `/forums.php?action=viewtopic&topicid=N`
 * Strangler Fig redirect, which intentionally does not run a DB
 * query before Laravel boots (the legacy entry point would have to
 * parse `.env` or load `dbconn.php` to learn the credentials).
 *
 * Two-hop redirect (legacy URL → here → canonical) is acceptable
 * because the legacy URL is hit only by bookmarks / RSS / cross-site
 * links; first-party navigation already uses the canonical URL
 * directly.
 *
 * Behaviour:
 *
 *   - Topic missing or soft-deleted → 404 (matches the legacy
 *     "topic not found" error surface).
 *   - Topic found → 302 to `/forum/{forumid}/topic/{topicid}`
 *     preserving any additional query string. `TopicView::mount()`
 *     then performs the proper `forumid` / `minclassread` validation.
 *   - When the query string carries `compose=reply` (set by the
 *     `/forums.php?action=reply&topicid=N` redirect), the `compose`
 *     param is dropped from the preserved query string and an
 *     `#reply` URL fragment is appended to the location header so the
 *     browser scrolls straight to the inline `ReplyForm` rendered at
 *     the bottom of TopicView.
 */
class ForumTopicRedirectController extends Controller
{
    public function __invoke(Request $request, int $topic): RedirectResponse
    {
        $row = Topic::query()->select(['id', 'forumid'])->find($topic);

        if ($row === null) {
            abort(404);
        }

        $query = $request->query();
        $compose = is_array($query) ? ($query['compose'] ?? null) : null;
        if (is_array($query) && array_key_exists('compose', $query)) {
            unset($query['compose']);
        }

        $qs = is_array($query) ? http_build_query($query) : '';
        $fragment = $compose === 'reply' ? '#reply' : '';

        $location = '/forum/'.(int) $row->forumid.'/topic/'.(int) $row->id
            .($qs !== '' ? '?'.$qs : '')
            .$fragment;

        return redirect($location, 302);
    }
}
