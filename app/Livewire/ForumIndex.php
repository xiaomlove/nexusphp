<?php

namespace App\Livewire;

use App\Models\Forum;
use App\Models\OverForum;
use App\Models\Post;
use App\Models\Topic;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modern Livewire forum index. Read-only: groups forums by overforum
 * (matching legacy /forums.php?action=index) and shows topic / post
 * counts plus the most recent post per forum.
 *
 * Posting, viewforum drilldown, and viewtopic still live on the legacy
 * /forums.php page — this is the foundation PR for an iterative rewrite.
 */
class ForumIndex extends Component
{
    /**
     * Live update: when any forum gets a new post, re-render so the
     * last-post column refreshes without a page reload. Empty body —
     * Livewire re-runs render() on listener invocation.
     *
     * @param  array<string, mixed>  $payload
     */
    #[On('echo:forums.activity,ForumPostAdded')]
    public function refreshOnForumPost(array $payload = []): void
    {
        // No-op — Livewire will re-render automatically.
    }

    public function render(): View
    {
        return view('livewire.forum-index', [
            'overforums' => $this->overforums,
            'forumsByOverforum' => $this->forumsByOverforum,
            'lastPosts' => $this->lastPosts,
        ])->layout('layouts.livewire-app', [
            'title' => 'Forum',
        ]);
    }

    /**
     * @return Collection<int, OverForum>
     */
    #[Computed]
    public function overforums(): Collection
    {
        return OverForum::query()
            ->where('minclassview', '<=', $this->userClass())
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Collection<int, Forum>>
     */
    #[Computed]
    public function forumsByOverforum(): Collection
    {
        return Forum::query()
            ->where('minclassread', '<=', $this->userClass())
            ->orderBy('forid')
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->groupBy('forid');
    }

    /**
     * Last post id + author + added timestamp per forum, keyed by forum id.
     *
     * Computed via a single sub-query batched per forum: cheaper than
     * eager-loading topics→posts, and consistent with how the legacy
     * page renders the "last post" cell.
     *
     * @return array<int, array{post_id: int, topic_id: int, topic_subject: string, user_id: int, username: string|null, added: Carbon|null}>
     */
    #[Computed]
    public function lastPosts(): array
    {
        $forumIds = $this->forumsByOverforum->collapse()->pluck('id')->all();
        if (empty($forumIds)) {
            return [];
        }

        $rows = Post::query()
            ->select([
                'posts.id as post_id',
                'posts.userid as user_id',
                'posts.added as added',
                'topics.id as topic_id',
                'topics.subject as topic_subject',
                'topics.forumid as forum_id',
                'users.username as username',
            ])
            ->join('topics', 'topics.id', '=', 'posts.topicid')
            ->leftJoin('users', 'users.id', '=', 'posts.userid')
            ->whereIn('topics.forumid', $forumIds)
            ->whereIn('posts.id', function ($q) use ($forumIds) {
                $q->from('posts as p2')
                    ->join('topics as t2', 't2.id', '=', 'p2.topicid')
                    ->whereIn('t2.forumid', $forumIds)
                    ->selectRaw('MAX(p2.id)')
                    ->groupBy('t2.forumid');
            })
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->forum_id] = [
                'post_id' => (int) $row->post_id,
                'topic_id' => (int) $row->topic_id,
                'topic_subject' => (string) $row->topic_subject,
                'user_id' => (int) $row->user_id,
                'username' => $row->username !== null ? (string) $row->username : null,
                'added' => $row->added,
            ];
        }

        return $out;
    }

    private function userClass(): int
    {
        $user = auth('nexus-web')->user();
        if ($user === null) {
            return 0;
        }

        return (int) ($user->class ?? 0);
    }

    public static function counts(?int $topiccount, ?int $postcount): string
    {
        return number_format((int) $topiccount).' / '.number_format((int) $postcount);
    }
}
