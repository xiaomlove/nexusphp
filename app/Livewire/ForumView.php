<?php

namespace App\Livewire;

use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Services\Exceptions\ForumReplyException;
use App\Services\ForumPostService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
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

    /** @var array<int, int> */
    public array $selectedTopicIds = [];

    public string $bulkAction = '';

    public int $bulkTargetForumId = 0;

    public int $bulkHlColor = 0;

    public ?string $bulkError = null;

    public ?string $bulkNotice = null;

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

    /**
     * Apply $bulkAction to every topic in $selectedTopicIds. Each
     * sub-action calls the existing single-topic ForumPostService
     * mutators so permission checks and side-effects (counts, cache
     * busts) stay consistent. Errors per-topic are aggregated into
     * $bulkError; successes are summarised in $bulkNotice.
     */
    public function applyBulkAction(ForumPostService $service): void
    {
        $this->bulkError = null;
        $this->bulkNotice = null;

        $user = auth('nexus-web')->user();
        $editorId = (int) ($user->id ?? 0);
        if ($editorId <= 0) {
            $this->bulkError = 'You must be logged in.';

            return;
        }
        if (! $service->canModerateForum($this->forumId, $editorId)) {
            $this->bulkError = 'You do not have permission to moderate this forum.';

            return;
        }

        $ids = array_values(array_filter(array_map('intval', $this->selectedTopicIds), fn (int $id): bool => $id > 0));
        if ($ids === []) {
            $this->bulkError = 'No topics selected.';

            return;
        }
        $action = $this->bulkAction;
        if ($action === '') {
            $this->bulkError = 'Pick a bulk action first.';

            return;
        }

        if ($action === 'move' && ($this->bulkTargetForumId <= 0 || $this->bulkTargetForumId === $this->forumId)) {
            $this->bulkError = 'Pick a different destination forum.';

            return;
        }

        $errors = [];
        $applied = 0;
        foreach ($ids as $topicId) {
            try {
                match ($action) {
                    'sticky-on' => $service->setSticky($topicId, $editorId, true),
                    'sticky-off' => $service->setSticky($topicId, $editorId, false),
                    'lock-on' => $service->setLocked($topicId, $editorId, true),
                    'lock-off' => $service->setLocked($topicId, $editorId, false),
                    'hlcolor' => $service->setHlColor($topicId, $editorId, $this->bulkHlColor),
                    'move' => $service->moveTopic($topicId, $editorId, $this->bulkTargetForumId),
                    default => throw new ForumReplyException('Unknown bulk action.'),
                };
                $applied++;
            } catch (ForumReplyException $e) {
                $errors[] = '#'.$topicId.': '.$e->getMessage();
            }
        }

        $this->selectedTopicIds = [];
        $this->bulkAction = '';
        $this->bulkTargetForumId = 0;
        $this->bulkHlColor = 0;

        $this->bulkNotice = $applied > 0
            ? "Applied to {$applied} topic".($applied === 1 ? '' : 's').'.'
            : null;
        if ($errors !== []) {
            $this->bulkError = implode(' | ', array_slice($errors, 0, 3))
                .(count($errors) > 3 ? ' (+'.(count($errors) - 3).' more)' : '');
        }
    }

    /**
     * Live update: when this forum gets a new post or topic, re-render
     * so the topic list (and its last-post column) refreshes without a
     * page reload. Posts in *other* forums are ignored. Empty body —
     * Livewire re-runs render() on listener invocation.
     *
     * @param  array<string, mixed>  $payload
     */
    #[On('echo:forums.activity,ForumPostAdded')]
    public function refreshOnForumPost(array $payload = []): void
    {
        if ((int) ($payload['forumId'] ?? 0) !== $this->forumId) {
            $this->skipRender();
        }
    }

    public function render(): View
    {
        $forum = $this->forum;
        $topics = $this->topics;
        $lastPostMeta = $this->lastPostMeta($topics->getCollection());

        $service = app(ForumPostService::class);
        $userId = (int) (auth('nexus-web')->user()?->id ?? 0);
        $canModerate = $userId > 0 && $service->canModerateForum($this->forumId, $userId);
        $availableForums = $canModerate
            ? Forum::query()->where('id', '!=', $this->forumId)->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('livewire.forum-view', [
            'forum' => $forum,
            'topics' => $topics,
            'lastPostMeta' => $lastPostMeta,
            'sortOptions' => $this->sortOptions(),
            'canModerate' => $canModerate,
            'availableForums' => $availableForums,
            'hlColors' => TopicView::HL_COLOR_OPTIONS,
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
