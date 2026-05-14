<?php

namespace App\Livewire;

use App\Models\Forum;
use App\Models\Topic;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Modern Livewire view of "topics with unread posts" — replaces the
 * legacy `/forums.php?action=viewunread`. Read-only: lists at most
 * `$perPage` unread topics ordered by `lastpost DESC`, with a
 * `beforepostid` cursor to page backwards through older unread
 * topics. The legacy "Catch up" write action stays on legacy for now
 * (separate PR — it touches `readposts` + `users.last_catchup`).
 *
 * Unread detection mirrors the legacy logic:
 *
 *   - `users.last_catchup` is the global "I've seen everything up to
 *     this post id" cursor.
 *   - `readposts(userid, topicid)` records per-topic `lastpostread`
 *     for topics the user has explicitly read past `last_catchup`.
 *   - A topic is unread iff its `lastpost` exceeds
 *     `max(readposts.lastpostread, users.last_catchup, 0)`.
 *
 * The legacy implementation looped topics in PHP and called
 * `get_last_read_post_id()` per topic, which hit `readposts` once
 * (cached by `$Cache->get_value`) and then served subsequent calls
 * from the in-memory cache. We do the same dance, but with a single
 * Eloquent fetch into a `topicid => lastpostread` map.
 *
 * Access control: topics in forums where `minclassread > user.class`
 * are filtered out before counting against `$perPage`, matching the
 * legacy `$uc < $a['minclassread']` continue.
 *
 * @property-read Collection<int, array<string, mixed>> $rows
 * @property-read int|null                              $nextCursor
 */
class ForumUnread extends Component
{
    public int $perPage = 25;

    /**
     * Cursor: the user clicked "Show more" on a previous page, so
     * fetch unread topics with `lastpost < $beforePostId`. Zero means
     * the first page.
     */
    #[Url(as: 'beforepostid', except: 0)]
    public int $beforePostId = 0;

    public function render(): View
    {
        return view('livewire.forum-unread', [
            'rows' => $this->rows,
            'nextCursor' => $this->nextCursor,
        ])->layout('layouts.livewire-app', [
            'title' => 'Unread topics',
        ]);
    }

    /**
     * Build the unread-topic list. Mirrors the legacy logic in
     * `public/forums.php` (action=viewunread):
     *
     *   1. Fetch the next batch of recently-active topics ordered by
     *      `lastpost DESC`, hard-capped at 100 (legacy constant) and
     *      filtered to `lastpost > users.last_catchup` (server-side
     *      pre-filter — a topic the user has "caught up" past cannot
     *      be unread).
     *   2. Fetch the user's `readposts` rows in one query and index
     *      them by `topicid`.
     *   3. For each candidate topic, compute the effective
     *      `lastpostread` as `max(readposts[topicid] ?? 0,
     *      last_catchup)`. Skip topics where `lastpostread >=
     *      lastpost`.
     *   4. Filter by forum visibility (`forum.minclassread`).
     *   5. Take the first `perPage` survivors.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed(persist: true)]
    public function rows(): Collection
    {
        $user = auth('nexus-web')->user();
        if ($user === null) {
            return collect();
        }

        $userId = (int) $user->id;
        $userClass = (int) ($user->class ?? 0);
        // `last_catchup` is a *post id* in storage, but the User
        // model casts it as `datetime:U` (legacy quirk). Bypass the
        // cast to read the raw integer — comparing against
        // `topics.lastpost` (also a post id) only makes sense when
        // both are raw ints.
        $lastCatchup = (int) ($user->getRawOriginal('last_catchup') ?? 0);

        // Step 1 — candidate topics (legacy fetches up to 100;
        // `beforepostid` rewinds further back when the user clicks
        // "Show more").
        $candidatesQuery = Topic::query()
            ->select(['id', 'forumid', 'subject', 'lastpost', 'hlcolor'])
            ->where('lastpost', '>', $lastCatchup);
        if ($this->beforePostId > 0) {
            $candidatesQuery->where('lastpost', '<', $this->beforePostId);
        }
        $candidates = $candidatesQuery->orderByDesc('lastpost')->limit(100)->get();
        if ($candidates->isEmpty()) {
            return collect();
        }

        // Step 2 — index readposts.
        $topicIds = $candidates->pluck('id')->all();
        $readPosts = \DB::connection($user->getConnectionName())
            ->table('readposts')
            ->where('userid', $userId)
            ->whereIn('topicid', $topicIds)
            ->pluck('lastpostread', 'topicid');

        // Step 3+4 — filter to unread + forum-visible.
        $forumIds = $candidates->pluck('forumid')->unique()->all();
        /** @var array<int, array{name: string, minclassread: int}> $forumMeta */
        $forumMeta = [];
        Forum::query()
            ->select(['id', 'name', 'minclassread'])
            ->whereIn('id', $forumIds)
            ->get()
            ->each(function ($f) use (&$forumMeta): void {
                $forumMeta[(int) $f->id] = [
                    'name' => (string) $f->name,
                    'minclassread' => (int) $f->minclassread,
                ];
            });

        $out = collect();
        foreach ($candidates as $row) {
            $topicId = (int) $row->id;
            $forumId = (int) $row->forumid;
            $forum = $forumMeta[$forumId] ?? null;
            if ($forum === null || $userClass < $forum['minclassread']) {
                continue;
            }

            // Effective last-read: max of explicit per-topic record
            // and the global catchup cursor.
            $lastPostRead = max((int) ($readPosts[$topicId] ?? 0), $lastCatchup);
            if ($lastPostRead >= (int) $row->lastpost) {
                continue;
            }

            $out->push([
                'id' => $topicId,
                'forum_id' => $forumId,
                'forum_name' => $forum['name'],
                'subject' => (string) $row->subject,
                'hlcolor' => (string) $row->hlcolor,
                'lastpost' => (int) $row->lastpost,
                'lastpostread' => $lastPostRead,
            ]);

            if ($out->count() >= $this->perPage) {
                break;
            }
        }

        return $out;
    }

    /**
     * The smallest `lastpost` in the current page, used as the
     * `beforepostid` cursor for the "Show more" link. Null when we
     * filled fewer than `perPage` slots — no further pages.
     */
    #[Computed]
    public function nextCursor(): ?int
    {
        $rows = $this->rows;
        if ($rows->count() < $this->perPage) {
            return null;
        }

        $tail = $rows->last();

        return is_array($tail) ? (int) ($tail['lastpost'] ?? 0) : null;
    }
}
