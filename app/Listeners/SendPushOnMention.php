<?php

namespace App\Listeners;

use App\Events\ForumPostAdded;
use App\Events\MessageCreated;
use App\Models\Message;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Services\PushDispatcher;
use App\Support\MentionExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

/**
 * Push @username mentions inside forum posts and PMs to every
 * referenced user, gated on User::acceptNotification('mention').
 *
 * Excludes the post author / message sender so self-mentions don't
 * notify, and excludes the topic author for forum posts (they already
 * get a 'topic_reply' push from {@see SendPushOnForumPost}).
 *
 * Queued so a slow VAPID provider never delays the write code path.
 */
class SendPushOnMention implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly PushDispatcher $push) {}

    public function handleForumPost(ForumPostAdded $event): void
    {
        $post = Post::query()->find($event->postId);
        if (! $post) {
            return;
        }
        $body = (string) ($post->body ?? '');
        $userIds = MentionExtractor::userIdsFromText($body, $event->userId);
        if (empty($userIds)) {
            return;
        }

        // Drop the topic author — they already get the dedicated reply push.
        $topic = Topic::query()->find($event->topicId);
        $topicAuthorId = (int) ($topic->userid ?? 0);
        if ($topicAuthorId > 0) {
            $userIds = array_values(array_filter(
                $userIds,
                fn (int $id): bool => $id !== $topicAuthorId,
            ));
            if (empty($userIds)) {
                return;
            }
        }

        $userIds = $this->filterByMentionOptIn($userIds);
        if (empty($userIds)) {
            return;
        }

        $sender = User::query()->find($event->userId);
        $senderName = $sender?->username ?: 'Someone';
        $subject = (string) Str::limit((string) ($topic->subject ?? ''), 80);
        $preview = (string) Str::limit(strip_tags($body), 140);

        $this->push->sendToUsers(
            $userIds,
            "Mentioned by {$senderName}".($subject !== '' ? ": {$subject}" : ''),
            $preview !== '' ? $preview : "{$senderName} mentioned you in a forum post.",
            '/forum/'.$event->forumId.'/topic/'.$event->topicId,
            [
                'kind' => 'forum_mention',
                'forumId' => $event->forumId,
                'topicId' => $event->topicId,
                'postId' => $event->postId,
            ],
        );
    }

    public function handleMessage(MessageCreated $event): void
    {
        $message = $event->model;
        if (! $message instanceof Message) {
            return;
        }
        $senderId = (int) ($message->sender ?? 0);
        $body = (string) ($message->msg ?? '');
        $userIds = MentionExtractor::userIdsFromText($body, $senderId);
        if (empty($userIds)) {
            return;
        }

        // Drop the recipient — they already got the dedicated PM push.
        $receiverId = (int) ($message->receiver ?? 0);
        if ($receiverId > 0) {
            $userIds = array_values(array_filter(
                $userIds,
                fn (int $id): bool => $id !== $receiverId,
            ));
            if (empty($userIds)) {
                return;
            }
        }

        $userIds = $this->filterByMentionOptIn($userIds);
        if (empty($userIds)) {
            return;
        }

        $sender = $senderId > 0 ? User::query()->find($senderId) : null;
        $senderName = $sender?->username ?: 'Someone';
        $subject = (string) Str::limit((string) ($message->subject ?? ''), 80);
        $preview = (string) Str::limit(strip_tags($body), 140);

        $this->push->sendToUsers(
            $userIds,
            "Mentioned by {$senderName}".($subject !== '' ? ": {$subject}" : ''),
            $preview !== '' ? $preview : "{$senderName} mentioned you in a message.",
            '/messages.php',
            ['kind' => 'message_mention', 'id' => (int) ($message->id ?? 0)],
        );
    }

    /**
     * @param  list<int>  $userIds
     * @return list<int>
     */
    private function filterByMentionOptIn(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }
        $users = User::query()->whereIn('id', $userIds)->get(['id', 'notifs']);

        return $users
            ->filter(fn (User $user): bool => $user->acceptNotification('mention'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
