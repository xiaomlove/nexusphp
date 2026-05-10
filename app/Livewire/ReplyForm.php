<?php

namespace App\Livewire;

use App\Models\Forum;
use App\Models\Topic;
use App\Services\Exceptions\ForumReplyException;
use App\Services\ForumPostService;
use Illuminate\Contracts\View\View;
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
