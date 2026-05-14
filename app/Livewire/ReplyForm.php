<?php

namespace App\Livewire;

use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Services\Exceptions\ForumReplyException;
use App\Services\ForumPostService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Inline reply form rendered at the bottom of TopicView. Submits via
 * Livewire (no page reload) and on success dispatches a Livewire event
 * that the parent picks up to jump to the last page.
 */
class ReplyForm extends Component
{
    public int $forumId = 0;

    public int $topicId = 0;

    #[Validate('required|string|min:1|max:65535')]
    public string $body = '';

    public ?string $errorMessage = null;

    public function mount(int $forumId, int $topicId): void
    {
        $this->forumId = $forumId;
        $this->topicId = $topicId;

        // The `?quote=N` query param is set by the
        // `/forums.php?action=quotepost&postid=N` Strangler Fig
        // redirect (see ForumPostRedirectController). If the post
        // belongs to this topic and is visible to the current user,
        // prefill the body with the [quote=author]…[/quote] block so
        // the user lands ready to type a reply.
        $quotePostId = (int) request()->query('quote', 0);
        if ($quotePostId > 0) {
            $this->prefillFromQuotePostId($quotePostId);
        }
    }

    private function prefillFromQuotePostId(int $postId): void
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
        $this->body = trim($this->body."\n".$quoted);
    }

    public function submit(ForumPostService $service): void
    {
        $this->errorMessage = null;
        $this->validate();

        $user = auth('nexus-web')->user();
        if ($user === null) {
            $this->errorMessage = 'Please log in to reply.';

            return;
        }

        try {
            $service->createReply(
                $this->forumId,
                $this->topicId,
                (int) $user->id,
                $this->body,
            );
        } catch (ForumReplyException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->reset('body');
        $this->dispatch('forum-reply-submitted', topicId: $this->topicId);
    }

    /**
     * Append a [quote=…]…[/quote] block dispatched from a parent
     * TopicView post action ('Quote' button). The body is appended to
     * any existing draft so users can quote multiple posts before
     * submitting.
     */
    #[On('quote-prefill')]
    public function prefillFromQuote(string $body): void
    {
        $this->body = trim($this->body."\n".$body);
    }

    public function render(): View
    {
        return view('livewire.reply-form', [
            'canPost' => $this->canPost(),
            'lockedReason' => $this->lockedReason(),
        ]);
    }

    private function canPost(): bool
    {
        return $this->lockedReason() === null;
    }

    private function lockedReason(): ?string
    {
        $user = auth('nexus-web')->user();
        if ($user === null) {
            return 'You must be logged in to reply.';
        }

        $forum = Forum::query()->find($this->forumId);
        $topic = Topic::query()->find($this->topicId);
        if ($forum === null || $topic === null) {
            return 'Topic is not available.';
        }
        if ((string) $topic->locked === 'yes') {
            return 'This topic is locked. Replies are disabled.';
        }
        $userClass = (int) ($user->class ?? 0);
        if ($userClass < (int) $forum->minclassread || $userClass < (int) $forum->minclasswrite) {
            return 'You do not have permission to reply in this forum.';
        }

        return null;
    }
}
