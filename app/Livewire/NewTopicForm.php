<?php

namespace App\Livewire;

use App\Models\Forum;
use App\Services\Exceptions\ForumReplyException;
use App\Services\ForumPostService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Compose UI for a brand-new topic. Lives at /forum/{forum}/new and
 * is a thin wrapper over ForumPostService::createTopic(). Redirects
 * to the freshly-created topic on success.
 */
class NewTopicForm extends Component
{
    public int $forumId = 0;

    #[Validate('required|string|min:1|max:255')]
    public string $subject = '';

    #[Validate('required|string|min:1|max:65535')]
    public string $body = '';

    public ?string $errorMessage = null;

    public function mount(int $forum): void
    {
        $forumRow = Forum::query()->findOrFail($forum);
        $user = auth('nexus-web')->user();
        $userClass = (int) ($user->class ?? 0);
        if (
            $userClass < (int) $forumRow->minclassread
            || $userClass < (int) $forumRow->minclasswrite
            || $userClass < (int) $forumRow->minclasscreate
        ) {
            abort(403);
        }

        $this->forumId = (int) $forumRow->id;
    }

    public function submit(ForumPostService $service): mixed
    {
        $this->errorMessage = null;
        $this->validate();

        $user = auth('nexus-web')->user();
        if ($user === null) {
            $this->errorMessage = 'Please log in to start a topic.';

            return null;
        }

        try {
            $result = $service->createTopic(
                $this->forumId,
                (int) $user->id,
                $this->subject,
                $this->body,
            );
        } catch (ForumReplyException $e) {
            $this->errorMessage = $e->getMessage();

            return null;
        }

        $topic = $result['topic'];

        return $this->redirectRoute(
            'forum.topic',
            ['forum' => $this->forumId, 'topic' => (int) $topic->id],
            navigate: true,
        );
    }

    public function render(): View
    {
        return view('livewire.new-topic-form', [
            'forum' => Forum::query()->findOrFail($this->forumId),
        ])->layout('layouts.livewire-app', [
            'title' => 'New topic',
        ]);
    }
}
