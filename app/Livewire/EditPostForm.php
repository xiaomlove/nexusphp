<?php

namespace App\Livewire;

use App\Models\Post;
use App\Models\Topic;
use App\Services\Exceptions\ForumReplyException;
use App\Services\ForumPostService;
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

    public function render(): View
    {
        return view('livewire.edit-post-form');
    }
}
