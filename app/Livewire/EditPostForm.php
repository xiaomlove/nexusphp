<?php

namespace App\Livewire;

use App\Models\Post;
use App\Models\Topic;
use App\Services\Exceptions\ForumReplyException;
use App\Services\ForumPostService;
use App\Support\PostDiff;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Inline edit form rendered inside a post when the user clicks Edit.
 * Mounted with the post id; loads the existing body (and subject when
 * editing the topic's first post) and on submit calls
 * {@see ForumPostService::editPost()}.
 */
class EditPostForm extends Component
{
    public int $postId = 0;

    public bool $isFirstPost = false;

    #[Validate('required|string|max:255')]
    public string $subject = '';

    #[Validate('required|string|min:1|max:65535')]
    public string $body = '';

    public ?string $errorMessage = null;

    public bool $showHistory = false;

    public function mount(int $postId): void
    {
        $post = Post::query()->findOrFail($postId);
        $topic = Topic::query()->findOrFail((int) $post->topicid);

        $service = app(ForumPostService::class);
        $editor = auth('nexus-web')->user();
        if ($editor === null || ! $service->canEditPost($postId, (int) $editor->id)) {
            abort(403);
        }

        $firstPostId = (int) Post::query()->where('topicid', $topic->id)->min('id');

        $this->postId = (int) $post->id;
        $this->isFirstPost = $firstPostId === (int) $post->id;
        $this->body = (string) $post->body;
        $this->subject = (string) $topic->subject;
    }

    public function submit(ForumPostService $service): void
    {
        $this->errorMessage = null;
        $this->validate();

        $editor = auth('nexus-web')->user();
        if ($editor === null) {
            $this->errorMessage = 'Please log in to edit this post.';

            return;
        }

        try {
            $service->editPost(
                $this->postId,
                (int) $editor->id,
                $this->body,
                $this->isFirstPost ? $this->subject : null,
            );
        } catch (ForumReplyException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->dispatch('post-edited', postId: $this->postId);
    }

    public function cancel(): void
    {
        $this->dispatch('post-edit-cancelled', postId: $this->postId);
    }

    public function toggleHistory(): void
    {
        $this->showHistory = ! $this->showHistory;
    }

    /**
     * Restore the post body / subject from a snapshot. Calls the
     * service which itself snapshots the current state first, so
     * a revert is reversible. Closes the form on success — the
     * parent component re-renders the post body from DB.
     */
    public function revertTo(int $editId, ForumPostService $service): void
    {
        $this->errorMessage = null;

        $editor = auth('nexus-web')->user();
        if ($editor === null) {
            $this->errorMessage = 'Please log in to revert this post.';

            return;
        }

        try {
            $service->revertPost($this->postId, (int) $editor->id, $editId);
        } catch (ForumReplyException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->dispatch('post-edited', postId: $this->postId);
    }

    public function render(): View
    {
        $service = app(ForumPostService::class);
        $history = $this->showHistory ? $service->getPostHistory($this->postId) : collect();
        $currentBody = $this->body;

        $diffs = [];
        if ($this->showHistory) {
            foreach ($history as $entry) {
                $diffs[(int) $entry->id] = PostDiff::lineDiff(
                    (string) ($entry->body_before ?? ''),
                    $currentBody,
                );
            }
        }

        return view('livewire.edit-post-form', [
            'history' => $history,
            'diffs' => $diffs,
        ]);
    }
}
