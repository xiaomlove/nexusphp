<?php

namespace App\Livewire;

use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Services\Exceptions\ForumReplyException;
use App\Services\ForumPostService;
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

    public int $editingPostId = 0;

    public ?string $deleteError = null;

    public ?string $modError = null;

    public bool $showMoveDialog = false;

    public int $moveTargetForumId = 0;

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

        // The `?edit=N` query param is set by the
        // `/forums.php?action=editpost&postid=N` Strangler Fig
        // redirect (see ForumPostRedirectController). If the post
        // belongs to this topic and the current user is allowed to
        // edit it, open the inline EditPostForm on mount so the user
        // lands ready to type.
        $editPostId = (int) request()->query('edit', 0);
        if ($editPostId > 0) {
            $service = app(ForumPostService::class);
            $editor = auth('nexus-web')->user();
            if ($editor !== null && $service->canEditPost($editPostId, (int) $editor->id)) {
                $belongs = Post::query()
                    ->where('id', $editPostId)
                    ->where('topicid', $this->topicId)
                    ->exists();
                if ($belongs) {
                    $this->editingPostId = $editPostId;
                }
            }
        }
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

    /**
     * Local update: ReplyForm dispatched 'forum-reply-submitted' after
     * a successful submit. Jump to the last page so the new post is
     * visible without scrolling.
     */
    #[On('forum-reply-submitted')]
    public function onReplySubmitted(int $topicId): void
    {
        if ($topicId !== $this->topicId) {
            $this->skipRender();

            return;
        }
        $this->gotoPage($this->posts->lastPage());
    }

    /**
     * Local update: EditPostForm finished a successful edit. Close the
     * inline edit panel and re-render so the post body refreshes.
     */
    #[On('post-edited')]
    public function onPostEdited(int $postId): void
    {
        if ($this->editingPostId === $postId) {
            $this->editingPostId = 0;
        }
    }

    /**
     * Local update: EditPostForm cancel button clicked.
     */
    #[On('post-edit-cancelled')]
    public function onPostEditCancelled(int $postId): void
    {
        if ($this->editingPostId === $postId) {
            $this->editingPostId = 0;
        }
    }

    public function startEditing(int $postId): void
    {
        $service = app(ForumPostService::class);
        $user = auth('nexus-web')->user();
        if ($user === null || ! $service->canEditPost($postId, (int) $user->id)) {
            return;
        }
        $this->editingPostId = $postId;
    }

    public function cancelEditing(): void
    {
        $this->editingPostId = 0;
    }

    public function quote(int $postId): void
    {
        $post = Post::query()->find($postId);
        if (! $post || (int) $post->topicid !== $this->topicId) {
            return;
        }
        $author = $post->user?->username ?? '';
        $body = (string) $post->body;
        $quoted = $author !== ''
            ? "[quote={$author}]\n{$body}\n[/quote]\n\n"
            : "[quote]\n{$body}\n[/quote]\n\n";

        $this->dispatch('quote-prefill', body: $quoted);
    }

    public function toggleSticky(ForumPostService $service): void
    {
        $this->modError = null;
        $user = auth('nexus-web')->user();
        if ($user === null) {
            return;
        }
        try {
            $service->setSticky($this->topicId, (int) $user->id, (string) $this->topic->sticky !== 'yes');
            unset($this->topic);
        } catch (ForumReplyException $e) {
            $this->modError = $e->getMessage();
        }
    }

    public function toggleLocked(ForumPostService $service): void
    {
        $this->modError = null;
        $user = auth('nexus-web')->user();
        if ($user === null) {
            return;
        }
        try {
            $service->setLocked($this->topicId, (int) $user->id, (string) $this->topic->locked !== 'yes');
            unset($this->topic);
        } catch (ForumReplyException $e) {
            $this->modError = $e->getMessage();
        }
    }

    public function setHlColor(int $color, ForumPostService $service): void
    {
        $this->modError = null;
        $user = auth('nexus-web')->user();
        if ($user === null) {
            return;
        }
        try {
            $service->setHlColor($this->topicId, (int) $user->id, $color);
            unset($this->topic);
        } catch (ForumReplyException $e) {
            $this->modError = $e->getMessage();
        }
    }

    public function moveTopic(ForumPostService $service): void
    {
        $this->modError = null;
        $user = auth('nexus-web')->user();
        if ($user === null) {
            return;
        }
        $target = $this->moveTargetForumId;
        if ($target <= 0 || $target === $this->forumId) {
            $this->modError = 'Pick a different destination forum.';

            return;
        }
        try {
            $service->moveTopic($this->topicId, (int) $user->id, $target);
        } catch (ForumReplyException $e) {
            $this->modError = $e->getMessage();

            return;
        }
        $this->showMoveDialog = false;
        $this->redirectRoute(
            'forum.topic',
            ['forum' => $target, 'topic' => $this->topicId],
            navigate: true,
        );
    }

    public function deleteTopic(ForumPostService $service): void
    {
        $this->modError = null;
        $user = auth('nexus-web')->user();
        if ($user === null) {
            return;
        }
        try {
            $result = $service->deleteTopic($this->topicId, (int) $user->id);
        } catch (ForumReplyException $e) {
            $this->modError = $e->getMessage();

            return;
        }
        $this->redirectRoute('forum.view', ['forum' => $result['forumId']], navigate: true);
    }

    public function deletePost(int $postId, ForumPostService $service): void
    {
        $this->deleteError = null;
        $user = auth('nexus-web')->user();
        if ($user === null) {
            $this->deleteError = 'Please log in to delete posts.';

            return;
        }

        try {
            $service->deletePost($postId, (int) $user->id);
        } catch (ForumReplyException $e) {
            $this->deleteError = $e->getMessage();

            return;
        }

        // Re-render via computed properties; current page may have shrunk.
        if ($this->editingPostId === $postId) {
            $this->editingPostId = 0;
        }
    }

    public function render(): View
    {
        $service = app(ForumPostService::class);
        $userId = (int) (auth('nexus-web')->user()?->id ?? 0);
        $editable = [];
        $deletable = [];
        if ($userId > 0) {
            foreach ($this->posts as $post) {
                $pid = (int) $post->id;
                $editable[$pid] = $service->canEditPost($pid, $userId);
                $deletable[$pid] = $service->canDeletePost($pid, $userId);
            }
        }
        $canModerate = $userId > 0 && $service->canModerateTopic($this->topicId, $userId);
        $availableForums = $canModerate
            ? Forum::query()
                ->where('id', '!=', $this->forumId)
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();
        $hlColors = self::HL_COLOR_OPTIONS;

        return view('livewire.topic-view', [
            'forum' => $this->forum,
            'topic' => $this->topic,
            'posts' => $this->posts,
            'editable' => $editable,
            'deletable' => $deletable,
            'canModerate' => $canModerate,
            'availableForums' => $availableForums,
            'hlColors' => $hlColors,
        ])->layout('layouts.livewire-app', [
            'title' => $this->topic->subject,
        ]);
    }

    /** @var array<int, string> */
    public const HL_COLOR_OPTIONS = [
        0 => 'No highlight',
        1 => 'Black',
        2 => 'Sienna',
        3 => 'DarkOliveGreen',
        4 => 'DarkGreen',
        5 => 'DarkSlateBlue',
        6 => 'Navy',
        7 => 'Indigo',
        8 => 'DarkSlateGray',
        9 => 'DarkRed',
        10 => 'DarkOrange',
        11 => 'Olive',
        12 => 'Green',
        13 => 'Teal',
        14 => 'Blue',
        15 => 'SlateGray',
        16 => 'DimGray',
        17 => 'Red',
        18 => 'SandyBrown',
        19 => 'YellowGreen',
        20 => 'SeaGreen',
        21 => 'MediumTurquoise',
        22 => 'RoyalBlue',
        23 => 'Purple',
        24 => 'Gray',
        25 => 'Magenta',
        26 => 'Orange',
        27 => 'Yellow',
        28 => 'Lime',
        29 => 'Cyan',
        30 => 'DeepSkyBlue',
        31 => 'DarkOrchid',
        32 => 'Silver',
        33 => 'Pink',
        34 => 'Wheat',
        35 => 'LemonChiffon',
        36 => 'PaleGreen',
    ];

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
