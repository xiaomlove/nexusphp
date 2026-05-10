<?php

namespace App\Services;

use App\Events\ForumPostAdded;
use App\Models\Forum;
use App\Models\Message;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Services\Exceptions\ForumReplyException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Domain service that creates a forum reply (and, in the future,
 * topics). Centralises the legacy forums.php?action=post code path so
 * Livewire components can reuse it without depending on IN_NEXUS
 * globals.
 *
 * Behaviour parity with public/forums.php (PR W steps 5-6):
 *   - Permission check (forum.minclassread + forum.minclasswrite).
 *   - Anti-flood: 10s gap between posts (skipped for postmanage).
 *   - Topic locked check (skipped for postmanage / forum mod).
 *   - KPS bonus: makepost (when bonus tweak enabled).
 *   - posts INSERT, topics.lastpost UPDATE, forums.postcount += 1,
 *     users.last_post timestamp.
 *   - Cache invalidation matching the legacy keys.
 *   - PM notification to topic author when not self-reply, gated on
 *     User::acceptNotification('topic_reply').
 *   - ForumPostAdded broadcast → Reverb (consumed by Livewire
 *     ForumIndex / ForumView / TopicView).
 *
 * What this service does NOT do:
 *   - New topics (createTopic) — separate follow-up.
 *   - Edit / delete — legacy /forums.php still owns those flows.
 *   - Forum moderator override of locked topics — keeps the simple
 *     path; users in that situation can still use the legacy form.
 */
final class ForumPostService
{
    private const FLOOD_SECONDS = 10;

    /**
     * @return array{post:Post,topic:Topic,forum:Forum}
     *
     * @throws ForumReplyException
     */
    public function createReply(int $forumId, int $topicId, int $userId, string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            throw new ForumReplyException('Reply body is required.');
        }

        $forum = Forum::query()->find($forumId);
        if (! $forum) {
            throw new ForumReplyException('Unknown forum.');
        }
        $topic = Topic::query()->find($topicId);
        if (! $topic) {
            throw new ForumReplyException('Unknown topic.');
        }
        if ((int) $topic->forumid !== (int) $forum->id) {
            throw new ForumReplyException('Topic does not belong to this forum.');
        }

        $user = User::query()->find($userId);
        if (! $user) {
            throw new ForumReplyException('Unknown user.');
        }
        $userClass = (int) ($user->class ?? 0);
        if ($userClass < (int) $forum->minclassread || $userClass < (int) $forum->minclasswrite) {
            throw new ForumReplyException('You do not have permission to reply in this forum.');
        }

        if ((string) $topic->locked === 'yes') {
            throw new ForumReplyException('Topic is locked.');
        }

        $lastPost = $user->last_post ? Carbon::parse((string) $user->last_post) : null;
        if ($lastPost !== null) {
            $diff = now()->getTimestamp() - $lastPost->getTimestamp();
            if ($diff >= 0 && $diff < self::FLOOD_SECONDS) {
                throw new ForumReplyException(sprintf('Please wait %d seconds before posting again.', self::FLOOD_SECONDS - $diff));
            }
        }

        $now = now();
        $post = DB::transaction(function () use ($forum, $topic, $user, $body, $now) {
            $post = Post::query()->create([
                'topicid' => $topic->id,
                'userid' => $user->id,
                'added' => $now,
                'body' => $body,
                'ori_body' => $body,
            ]);

            Topic::query()->where('id', $topic->id)->update(['lastpost' => $post->id]);
            Forum::query()->where('id', $forum->id)->increment('postcount');
            User::query()->where('id', $user->id)->update(['last_post' => $now]);

            return $post;
        });

        $this->awardBonus($user->id);
        $this->bustCaches((int) $forum->id, (int) $topic->id, (int) $user->id);
        $this->notifyTopicAuthor($forum, $topic, $post, $user);

        try {
            ForumPostAdded::dispatch(
                (int) $forum->id,
                (int) $topic->id,
                (int) $post->id,
                (int) $user->id,
                false,
            );
        } catch (\Throwable $e) {
            Log::warning('[forum] ForumPostAdded broadcast failed: '.$e->getMessage());
        }

        // Refresh topic for caller (lastpost just changed).
        $topic = Topic::query()->find($topic->id) ?? $topic;

        return ['post' => $post, 'topic' => $topic, 'forum' => $forum];
    }

    private function awardBonus(int $userId): void
    {
        if (! function_exists('get_setting')) {
            return;
        }
        $tweak = (string) get_setting('tweak.bonus', '');
        if ($tweak !== 'enable' && $tweak !== 'disablesave') {
            return;
        }
        $points = (float) get_setting('bonus.makepost', 0);
        if ($points === 0.0) {
            return;
        }

        try {
            User::query()->where('id', $userId)->update([
                'seedbonus' => DB::raw('seedbonus + '.$points),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[forum] makepost bonus failed: '.$e->getMessage());
        }
    }

    private function bustCaches(int $forumId, int $topicId, int $userId): void
    {
        $today = date('Y-m-d');
        foreach ([
            'forum_'.$forumId.'_post_'.$today.'_count',
            'today_'.$today.'_posts_count',
            'forum_'.$forumId.'_last_replied_topic_content',
            'topic_'.$topicId.'_post_count',
            'user_'.$userId.'_post_count',
        ] as $key) {
            try {
                Cache::forget($key);
            } catch (\Throwable) {
                // Cache backend can be unreachable; ignore.
            }
        }
    }

    private function notifyTopicAuthor(Forum $forum, Topic $topic, Post $post, User $replier): void
    {
        $authorId = (int) ($topic->userid ?? 0);
        if ($authorId <= 0 || $authorId === (int) $replier->id) {
            return;
        }
        $author = User::query()->find($authorId);
        if (! $author || ! $author->acceptNotification('topic_reply')) {
            return;
        }

        $postUrl = sprintf(
            '[url=forums.php?action=viewtopic&topicid=%s&page=p%s#pid%s]%s[/url]',
            $topic->id,
            $post->id,
            $post->id,
            (string) $topic->subject,
        );
        $locale = $author->locale ?? null;

        try {
            Message::add([
                'sender' => 0,
                'receiver' => $author->id,
                'subject' => function_exists('nexus_trans')
                    ? nexus_trans('forum.topic.replied_notify_subject', [], $locale)
                    : 'Your topic has a new reply',
                'msg' => function_exists('nexus_trans')
                    ? nexus_trans('forum.topic.replied_notify_body', ['topic_subject' => $postUrl], $locale)
                    : 'A reply has been posted to your topic: '.$postUrl,
                'added' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[forum] reply PM notify failed: '.$e->getMessage());
        }
    }
}
