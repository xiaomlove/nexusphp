<?php

namespace App\Livewire;

use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Modern Livewire view of a single forum: lists topics with sticky
 * pinning, last-post info, and reply/view counts. Read-only — posting,
 * replying, and per-topic moderation still live on /forums.php.
 */
class ForumView extends Component
{
    use WithPagination;

    public int $forumId = 0;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: 'lastpost-desc')]
    public string $sort = 'lastpost-desc';

    public int $perPage = 30;

    private const SORTS = [
        'lastpost-desc' => ['column' => 'lastpost', 'direction' => 'desc', 'label' => 'Latest reply'],
        'lastpost-asc' => ['column' => 'lastpost', 'direction' => 'asc', 'label' => 'Oldest reply'],
        'firstpost-desc' => ['column' => 'firstpost', 'direction' => 'desc', 'label' => 'Newest topic'],
        'firstpost-asc' => ['column' => 'firstpost', 'direction' => 'asc', 'label' => 'Oldest topic'],
        'views-desc' => ['column' => 'views', 'direction' => 'desc', 'label' => 'Most viewed'],
    ];

    public function mount(int $forum): void
    {
        $row = Forum::query()->findOrFail($forum);
        $user = auth('nexus-web')->user();
        $userClass = (int) ($user->class ?? 0);
        if ($userClass < (int) $row->minclassread) {
            abort(403);
        }
        $this->forumId = (int) $row->id;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'sort']);
        $this->sort = 'lastpost-desc';
        $this->resetPage();
    }

    public function render(): View
    {
        $forum = $this->forum;
        $topics = $this->topics;
        $lastPostMeta = $this->lastPostMeta($topics->getCollection());

        return view('livewire.forum-view', [
            'forum' => $forum,
            'topics' => $topics,
            'lastPostMeta' => $lastPostMeta,
            'sortOptions' => $this->sortOptions(),
        ])->layout('layouts.livewire-app', [
            'title' => $forum->name,
        ]);
    }

    #[Computed]
    public function forum(): Forum
    {
        return Forum::query()->findOrFail($this->forumId);
    }

    /**
     * @return LengthAwarePaginator<Topic>
     */
    #[Computed]
    public function topics(): LengthAwarePaginator
    {
        $config = self::SORTS[$this->sort] ?? self::SORTS['lastpost-desc'];

        $query = Topic::query()->where('forumid', $this->forumId);

        if ($this->search !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], $this->search).'%';
            $query->where('subject', 'like', $needle);
        }

        $query->orderByRaw("CASE WHEN sticky = 'yes' THEN 0 ELSE 1 END")
            ->orderBy($config['column'], $config['direction']);

        return $query->paginate($this->perPage);
    }

    /**
     * @return array<int, array{label: string, key: string}>
     */
    public function sortOptions(): array
    {
        $out = [];
        foreach (self::SORTS as $key => $cfg) {
            $out[] = ['key' => $key, 'label' => $cfg['label']];
        }

        return $out;
    }

    /**
     * @param  Collection<int, Topic>  $topics
     * @return array<int, array{username: ?string, userid: int, added: ?string}>
     */
    private function lastPostMeta(Collection $topics): array
    {
        $postIds = $topics->pluck('lastpost')->filter()->map(fn ($v) => (int) $v)->all();
        if (empty($postIds)) {
            return [];
        }

        $rows = Post::query()
            ->select(['posts.id', 'posts.userid', 'posts.added', 'posts.topicid', 'users.username'])
            ->leftJoin('users', 'users.id', '=', 'posts.userid')
            ->whereIn('posts.id', $postIds)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->id] = [
                'username' => $row->username !== null ? (string) $row->username : null,
                'userid' => (int) $row->userid,
                'added' => $row->added !== null ? (string) $row->added : null,
            ];
        }

        return $out;
    }

    public static function formatCounts(?int $count): string
    {
        return number_format((int) $count);
    }
}
