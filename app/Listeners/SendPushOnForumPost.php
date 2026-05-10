<?php

namespace App\Listeners;

use App\Events\ForumPostAdded;
use App\Models\Topic;
use App\Models\User;
use App\Services\PushDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

/**
 * On a forum post / new topic, push a notification to the topic
 * author (when the post is a reply by someone else and the author
 * opts in via 'topic_reply').
 *
 * Queued so a slow VAPID provider never delays the post-write code
 * path.
 */
class SendPushOnForumPost implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly PushDispatcher $push) {}

    public function handle(ForumPostAdded $event): void
    {
        if ($event->newTopic) {
            return;
        }

        $topic = Topic::query()->find($event->topicId);
        if (! $topic) {
            return;
        }

        $authorId = (int) ($topic->userid ?? 0);
        if ($authorId <= 0 || $authorId === $event->userId) {
            return;
        }

        $author = User::query()->find($authorId);
        if (! $author || ! $author->acceptNotification('topic_reply')) {
            return;
        }

        $replyer = User::query()->find($event->userId);
        $replyerName = $replyer?->username ?: 'Someone';
        $subject = (string) Str::limit((string) ($topic->subject ?? ''), 80);

        $this->push->sendToUser(
            $authorId,
            "New reply: {$subject}",
            "{$replyerName} replied to your topic.",
            '/forum/'.$event->forumId.'/topic/'.$event->topicId,
            [
                'kind' => 'forum_reply',
                'forumId' => $event->forumId,
                'topicId' => $event->topicId,
                'postId' => $event->postId,
            ],
        );
    }
}
