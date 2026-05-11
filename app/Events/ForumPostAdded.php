<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired (and broadcast over Reverb) when a forum post is created or
 * an existing topic gets a new reply. The Livewire ForumIndex and
 * ForumView components listen on the public `forums.activity` channel
 * and refresh their last-post columns / topic order without a page
 * reload.
 */
class ForumPostAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  int  $forumId  Forum the post belongs to.
     * @param  int  $topicId  Topic the post belongs to.
     * @param  int  $postId  The newly created post id.
     * @param  int  $userId  Post author.
     * @param  bool  $newTopic  True when this post is the first post of a brand-new topic.
     */
    public function __construct(
        public int $forumId,
        public int $topicId,
        public int $postId,
        public int $userId,
        public bool $newTopic = false,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('forums.activity')];
    }

    public function broadcastAs(): string
    {
        return 'ForumPostAdded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'forumId' => $this->forumId,
            'topicId' => $this->topicId,
            'postId' => $this->postId,
            'userId' => $this->userId,
            'newTopic' => $this->newTopic,
        ];
    }
}
