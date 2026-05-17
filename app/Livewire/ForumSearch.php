<?php

namespace App\Livewire;

use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Replaces legacy `/forums.php?action=search`. Searches `posts.body`
 * and the first post's `topics.subject` for the `keywords` substring,
 * filtered by `forums.minclassread <= user.class`.
 */
class ForumSearch extends Component
{
    use WithPagination;

    #[Url(as: 'keywords', except: '')]
    public string $keywords = '';

    public int $perPage = 25;

    public function render(): View
    {
        return view('livewire.forum-search', [
            'results' => $this->results(),
        ])->layout('layouts.livewire-app', [
            'title' => 'Forum search',
        ]);
    }

    public function updatingKeywords(): void
    {
        $this->resetPage();
    }

    public function clear(): void
    {
        $this->keywords = '';
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, object>
     */
    private function results(): LengthAwarePaginator
    {
        $user = auth('nexus-web')->user();
        $userClass = (int) ($user->class ?? 0);
        $needle = trim($this->keywords);

        $query = Post::query()
            ->from('posts')
            ->leftJoin('topics', 'posts.topicid', '=', 'topics.id')
            ->leftJoin('forums', 'topics.forumid', '=', 'forums.id')
            ->where('forums.minclassread', '<=', $userClass)
            ->select([
                'posts.id as post_id',
                'posts.topicid',
                'posts.userid',
                'posts.added',
                'topics.subject',
                'topics.hlcolor',
                'forums.id as forumid',
                'forums.name as forumname',
            ])
            ->orderBy('posts.id', 'desc');

        if ($needle === '') {
            $query->whereRaw('1 = 0');
        } else {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
            $query->where(function ($q) use ($like) {
                $q->where(function ($q2) use ($like) {
                    $q2->where('topics.subject', 'like', $like)
                        ->whereColumn('posts.id', 'topics.firstpost');
                })->orWhere('posts.body', 'like', $like);
            });
        }

        return $query->paginate($this->perPage);
    }
}
