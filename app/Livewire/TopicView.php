<?php

namespace App\Livewire;

use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Support\BbcodeRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Modern Livewire view of a single topic: paginated posts, read-only.
 * Posting/replying/editing still lives on /forums.php — the legacy
 * link in the header opens that flow with one click.
 *
 * Post bodies are rendered as escaped + linebreak-preserving plain
 * text. Full BBCode → HTML rendering is intentionally deferred to
 * the BBCode→Markdown migration PR; for now, users can click the
 * legacy link to see fully-formatted posts.
 */
class TopicView extends Component
{
    use WithPagination;

    public int $topicId = 0;

    public int $forumId = 0;

    #[Url(as: 'author', except: 0)]
    public int $authorFilter = 0;

    public int $perPage = 20;

    public function mount(int $forum, int $topic): void
    {
        $forumRow = Forum::query()->findOrFail($forum);
        $topicRow = Topic::query()->findOrFail($topic);

        if ((int) $topicRow->forumid !== (int) $forumRow->id) {
            abort(404);
        }

        $user = auth('nexus-web')->user();
        $userClass = (int) ($user->class ?? 0);
        if ($userClass < (int) $forumRow->minclassread) {
            abort(403);
        }

        $this->forumId = (int) $forumRow->id;
        $this->topicId = (int) $topicRow->id;

        // Bump the views counter, matching legacy behaviour. Skip on
        // pagination round-trips — only count the first render.
        Topic::query()->where('id', $this->topicId)->increment('views');
    }

    public function updatingAuthorFilter(): void
    {
        $this->resetPage();
    }

    public function clearAuthorFilter(): void
    {
        $this->authorFilter = 0;
        $this->resetPage();
    }

    /**
     * Live update: when a new post is added to *this* topic, re-render
     * so the post list picks up the reply. Posts in other topics are
     * ignored. Empty body — Livewire re-runs render() on listener
     * invocation.
     *
     * @param  array<string, mixed>  $payload
     */
    #[On('echo:forums.activity,ForumPostAdded')]
    public function refreshOnForumPost(array $payload = []): void
    {
        if ((int) ($payload['topicId'] ?? 0) !== $this->topicId) {
            $this->skipRender();
        }
    }

    public function render(): View
    {
        return view('livewire.topic-view', [
            'forum' => $this->forum,
            'topic' => $this->topic,
            'posts' => $this->posts,
        ])->layout('layouts.livewire-app', [
            'title' => $this->topic->subject,
        ]);
    }

    #[Computed]
    public function forum(): Forum
    {
        return Forum::query()->findOrFail($this->forumId);
    }

    #[Computed]
    public function topic(): Topic
    {
        return Topic::query()->findOrFail($this->topicId);
    }

    /**
     * @return LengthAwarePaginator<Post>
     */
    #[Computed]
    public function posts(): LengthAwarePaginator
    {
        $query = Post::query()
            ->select([
                'posts.id',
                'posts.topicid',
                'posts.userid',
                'posts.added',
                'posts.body',
                'posts.editedby',
                'posts.editdate',
                'users.username as author_username',
                'users.class as author_class',
                'users.avatar as author_avatar',
                'users.signature as author_signature',
            ])
            ->leftJoin('users', 'users.id', '=', 'posts.userid')
            ->where('posts.topicid', $this->topicId)
            ->orderBy('posts.added', 'asc')
            ->orderBy('posts.id', 'asc');

        if ($this->authorFilter > 0) {
            $query->where('posts.userid', $this->authorFilter);
        }

        return $query->paginate($this->perPage);
    }

    /**
     * Render a post body as safe HTML through {@see BbcodeRenderer}.
     * Unsupported tags (hide, spoiler, attach, youtube, video) are left
     * as escaped literal text — users can click the legacy chip in the
     * header to see the full server-side render.
     */
    public static function renderBody(?string $body): string
    {
        return BbcodeRenderer::toHtml($body);
    }
}
