<?php

namespace App\Services;

use App\Events\ForumPostAdded;
use App\Models\Forum;
use App\Models\Message;
use App\Models\Post;
use App\Models\PostEdit;
use App\Models\Topic;
use App\Models\User;
use App\Services\Exceptions\ForumReplyException;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Domain service that owns forum-post mutations (reply / new topic /
 * edit / delete). Centralises the legacy forums.php?action=post|edit|
 * delete code paths so Livewire components can reuse them without
 * depending on IN_NEXUS globals.
 *
 * Behaviour parity with public/forums.php:
 *   - Permission check (forum.minclassread + minclasswrite + minclasscreate
 *     for new topics; postmanage / forum moderator overrides for edit
 *     and delete).
 *   - Anti-flood: 10s gap between posts (createReply / createTopic).
 *   - Topic locked check (skipped for postmanage / forum mod on edit).
 *   - KPS bonus: makepost / starttopic awarded on insert, makepost
 *     revoked on delete.
 *   - DB writes: posts INSERT/UPDATE/DELETE, topics firstpost/lastpost
 *     bookkeeping, forums.topiccount + postcount, users.last_post.
 *   - Cache invalidation matching the legacy keys (post_*_content,
 *     forum_*_last_replied_topic_content, …).
 *   - PM notifications:
 *       - reply → topic author (gated on accepts 'topic_reply')
 *       - edit → original author when editor is someone else
 *   - ForumPostAdded broadcast → Reverb (consumed by Livewire
 *     ForumIndex / ForumView / TopicView).
 *
 * What this service does NOT do:
 *   - Topic-level deletion (legacy ?action=deletetopic still owns full
 *     topic teardown, including readposts / subscriptions cleanup).
 *   - Topic moderation actions (sticky, lock, hlcolor, move).
 *   - Quote auto-prefill (the Livewire reply form handles prefill on
 *     the client side).
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

    /**
     * Create a new topic in a forum + first post in a single transaction.
     *
     * @return array{post:Post,topic:Topic,forum:Forum}
     *
     * @throws ForumReplyException
     */
    public function createTopic(int $forumId, int $userId, string $subject, string $body): array
    {
        $subject = trim($subject);
        $body = trim($body);

        if ($subject === '') {
            throw new ForumReplyException('Topic subject is required.');
        }
        if (mb_strlen($subject) > 255) {
            throw new ForumReplyException('Topic subject is too long.');
        }
        if ($body === '') {
            throw new ForumReplyException('Topic body is required.');
        }

        $forum = Forum::query()->find($forumId);
        if (! $forum) {
            throw new ForumReplyException('Unknown forum.');
        }
        $user = User::query()->find($userId);
        if (! $user) {
            throw new ForumReplyException('Unknown user.');
        }
        $userClass = (int) ($user->class ?? 0);
        if (
            $userClass < (int) $forum->minclassread
            || $userClass < (int) $forum->minclasswrite
            || $userClass < (int) $forum->minclasscreate
        ) {
            throw new ForumReplyException('You do not have permission to start a topic in this forum.');
        }

        $lastPost = $user->last_post ? Carbon::parse((string) $user->last_post) : null;
        if ($lastPost !== null) {
            $diff = now()->getTimestamp() - $lastPost->getTimestamp();
            if ($diff >= 0 && $diff < self::FLOOD_SECONDS) {
                throw new ForumReplyException(sprintf('Please wait %d seconds before posting again.', self::FLOOD_SECONDS - $diff));
            }
        }

        $now = now();
        [$topic, $post] = DB::transaction(function () use ($forum, $user, $subject, $body, $now) {
            $topic = Topic::query()->create([
                'userid' => $user->id,
                'forumid' => $forum->id,
                'subject' => $subject,
            ]);
            $post = Post::query()->create([
                'topicid' => $topic->id,
                'userid' => $user->id,
                'added' => $now,
                'body' => $body,
                'ori_body' => $body,
            ]);
            Topic::query()->where('id', $topic->id)->update([
                'firstpost' => $post->id,
                'lastpost' => $post->id,
            ]);
            Forum::query()->where('id', $forum->id)->update([
                'topiccount' => DB::raw('topiccount + 1'),
                'postcount' => DB::raw('postcount + 1'),
            ]);
            User::query()->where('id', $user->id)->update(['last_post' => $now]);

            return [$topic->fresh() ?? $topic, $post];
        });

        $this->awardBonus($user->id, 'starttopic');
        $this->bustCaches((int) $forum->id, (int) $topic->id, (int) $user->id);

        try {
            ForumPostAdded::dispatch(
                (int) $forum->id,
                (int) $topic->id,
                (int) $post->id,
                (int) $user->id,
                true,
            );
        } catch (\Throwable $e) {
            Log::warning('[forum] ForumPostAdded broadcast failed: '.$e->getMessage());
        }

        return ['post' => $post, 'topic' => $topic, 'forum' => $forum];
    }

    /**
     * Award the makepost / starttopic bonus when the bonus tweak is enabled.
     */
    private function awardBonus(int $userId, string $kind = 'makepost'): void
    {
        if (! function_exists('get_setting')) {
            return;
        }
        $tweak = (string) get_setting('tweak.bonus', '');
        if ($tweak !== 'enable' && $tweak !== 'disablesave') {
            return;
        }
        $key = $kind === 'starttopic' ? 'bonus.starttopic' : 'bonus.makepost';
        $points = (float) get_setting($key, 0);
        if ($points === 0.0) {
            return;
        }

        try {
            User::query()->where('id', $userId)->update([
                'seedbonus' => DB::raw('seedbonus + '.$points),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[forum] '.$kind.' bonus failed: '.$e->getMessage());
        }
    }

    /**
     * Edit an existing post. Author can edit their own post (unless the topic
     * is locked); moderators / postmanage can edit anyone's post even when
     * locked. When editing the topic's first post, the new subject (if
     * supplied) replaces topics.subject.
     *
     * @return array{post:Post,topic:Topic,forum:Forum}
     *
     * @throws ForumReplyException
     */
    public function editPost(int $postId, int $editorId, string $body, ?string $subject = null): array
    {
        $body = trim($body);
        if ($body === '') {
            throw new ForumReplyException('Post body is required.');
        }

        $post = Post::query()->find($postId);
        if (! $post) {
            throw new ForumReplyException('Unknown post.');
        }
        $topic = Topic::query()->find((int) $post->topicid);
        if (! $topic) {
            throw new ForumReplyException('Unknown topic.');
        }
        $forum = Forum::query()->find((int) $topic->forumid);
        if (! $forum) {
            throw new ForumReplyException('Unknown forum.');
        }
        $editor = User::query()->find($editorId);
        if (! $editor) {
            throw new ForumReplyException('Unknown user.');
        }

        $isAuthor = (int) $post->userid === (int) $editor->id;
        $isMod = $this->isForumModerator((int) $forum->id, (int) $editor->id);
        $canManage = $this->userCan($editor, 'postmanage');
        $locked = (string) $topic->locked === 'yes';

        if (($locked || ! $isAuthor) && ! $isMod && ! $canManage) {
            throw new ForumReplyException('You do not have permission to edit this post.');
        }

        $firstPostId = (int) Post::query()->where('topicid', $topic->id)->min('id');
        $isFirstPost = $firstPostId === (int) $post->id;

        $now = now();
        $newSubject = null;
        if ($isFirstPost && $subject !== null) {
            $newSubject = trim($subject);
            if ($newSubject === '') {
                throw new ForumReplyException('Topic subject is required.');
            }
            if (mb_strlen($newSubject) > 255) {
                throw new ForumReplyException('Topic subject is too long.');
            }
        }

        $previousBody = (string) ($post->body ?? '');
        $previousSubject = $isFirstPost ? (string) ($topic->subject ?? '') : null;
        $bodyChanged = $previousBody !== $body;
        $subjectChanged = $newSubject !== null && $newSubject !== (string) $topic->subject;

        DB::transaction(function () use ($post, $topic, $editor, $body, $newSubject, $now, $previousBody, $previousSubject, $bodyChanged, $subjectChanged) {
            if ($bodyChanged || $subjectChanged) {
                PostEdit::query()->insert([
                    'postid' => $post->id,
                    'editor_userid' => $editor->id,
                    'body_before' => $previousBody,
                    'subject_before' => $previousSubject,
                    'edited_at' => $now,
                ]);
            }
            Post::query()->where('id', $post->id)->update([
                'body' => $body,
                'editdate' => $now,
                'editedby' => $editor->id,
            ]);
            if ($subjectChanged && $newSubject !== null) {
                Topic::query()->where('id', $topic->id)->update(['subject' => $newSubject]);
            }
        });

        try {
            Cache::forget('post_'.$post->id.'_content');
            if ($newSubject !== null) {
                Cache::forget('forum_'.$forum->id.'_last_replied_topic_content');
            }
        } catch (\Throwable) {
            // Cache backend can be unreachable; ignore.
        }

        $this->notifyEditedAuthor($topic->fresh() ?? $topic, $post->fresh() ?? $post, $editor);

        $post = $post->fresh() ?? $post;
        $topic = $topic->fresh() ?? $topic;

        return ['post' => $post, 'topic' => $topic, 'forum' => $forum];
    }

    /**
     * Delete a post. Only forum moderators or users with postmanage may
     * call this; the first post of a topic cannot be deleted via this
     * method (legacy /forums.php?action=deletetopic still owns full-topic
     * removal).
     *
     * @return array{topic:Topic,forum:Forum,prevPostId:int|null}
     *
     * @throws ForumReplyException
     */
    public function deletePost(int $postId, int $editorId): array
    {
        $post = Post::query()->find($postId);
        if (! $post) {
            throw new ForumReplyException('Unknown post.');
        }
        $topic = Topic::query()->find((int) $post->topicid);
        if (! $topic) {
            throw new ForumReplyException('Unknown topic.');
        }
        $forum = Forum::query()->find((int) $topic->forumid);
        if (! $forum) {
            throw new ForumReplyException('Unknown forum.');
        }
        $editor = User::query()->find($editorId);
        if (! $editor) {
            throw new ForumReplyException('Unknown user.');
        }

        $isMod = $this->isForumModerator((int) $forum->id, (int) $editor->id);
        $canManage = $this->userCan($editor, 'postmanage');
        if (! $isMod && ! $canManage) {
            throw new ForumReplyException('You do not have permission to delete this post.');
        }

        $prevPostId = Post::query()
            ->where('topicid', $topic->id)
            ->where('id', '<', $post->id)
            ->orderByDesc('id')
            ->value('id');
        if ($prevPostId === null) {
            throw new ForumReplyException('Cannot delete the first post of a topic. Delete the whole topic instead via the legacy view.');
        }

        $authorId = (int) $post->userid;

        DB::transaction(function () use ($post, $forum) {
            Post::query()->where('id', $post->id)->delete();
            Forum::query()->where('id', $forum->id)->update([
                'postcount' => DB::raw('GREATEST(postcount - 1, 0)'),
            ]);
        });

        // Recompute topics.lastpost from remaining posts.
        $newLastPost = (int) Post::query()
            ->where('topicid', $topic->id)
            ->orderByDesc('id')
            ->value('id');
        if ($newLastPost > 0) {
            Topic::query()->where('id', $topic->id)->update(['lastpost' => $newLastPost]);
        }

        // Revoke makepost bonus from the original author.
        if ($authorId > 0) {
            $this->revokeBonus($authorId);
        }

        $this->bustCaches((int) $forum->id, (int) $topic->id, $authorId);

        return ['topic' => $topic->fresh() ?? $topic, 'forum' => $forum, 'prevPostId' => (int) $prevPostId];
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

    /**
     * Toggle the sticky flag on a topic. Requires forum moderator or
     * postmanage. Returns the fresh topic.
     *
     * @throws ForumReplyException
     */
    public function setSticky(int $topicId, int $editorId, bool $sticky): Topic
    {
        [$topic, $forum] = $this->ensureTopicMod($topicId, $editorId);
        Topic::query()->where('id', $topic->id)->update(['sticky' => $sticky ? 'yes' : 'no']);
        $this->bustForumLastReplied($forum->id);

        return $topic->fresh() ?? $topic;
    }

    /**
     * Toggle the locked flag on a topic. Requires forum moderator or
     * postmanage. Returns the fresh topic.
     *
     * @throws ForumReplyException
     */
    public function setLocked(int $topicId, int $editorId, bool $locked): Topic
    {
        [$topic, $forum] = $this->ensureTopicMod($topicId, $editorId);
        Topic::query()->where('id', $topic->id)->update(['locked' => $locked ? 'yes' : 'no']);
        $this->bustForumLastReplied($forum->id);

        return $topic->fresh() ?? $topic;
    }

    /**
     * Set the topic title highlight colour (0 = clear). Requires forum
     * moderator or postmanage. Colour must be 0..40 — if the legacy
     * get_hl_color() helper is loaded, additionally validates against
     * its switch table (returns a name or false).
     *
     * @throws ForumReplyException
     */
    public function setHlColor(int $topicId, int $editorId, int $color): Topic
    {
        [$topic, $forum] = $this->ensureTopicMod($topicId, $editorId);
        if ($color < 0 || $color > 40) {
            throw new ForumReplyException('Invalid highlight colour.');
        }
        if ($color !== 0 && function_exists('get_hl_color')) {
            $name = get_hl_color($color);
            if (! is_string($name) || $name === '') {
                throw new ForumReplyException('Invalid highlight colour.');
            }
        }
        Topic::query()->where('id', $topic->id)->update(['hlcolor' => $color]);
        $this->bustForumLastReplied($forum->id);

        return $topic->fresh() ?? $topic;
    }

    /**
     * Move the topic to another forum. Requires forum moderator (on the
     * source) or postmanage; the editor must additionally have
     * minclasswrite on the destination forum (mirrors legacy
     * /forums.php?action=movetopic). Updates topiccount / postcount on
     * both forums and busts the relevant cache keys.
     *
     * @throws ForumReplyException
     */
    public function moveTopic(int $topicId, int $editorId, int $newForumId): Topic
    {
        [$topic, $oldForum] = $this->ensureTopicMod($topicId, $editorId);
        $newForum = Forum::query()->find($newForumId);
        if (! $newForum) {
            throw new ForumReplyException('Destination forum not found.');
        }
        if ((int) $newForum->id === (int) $oldForum->id) {
            return $topic;
        }

        $editor = User::query()->find($editorId);
        if (! $editor) {
            throw new ForumReplyException('Unknown user.');
        }
        $editorClass = (int) ($editor->class ?? 0);
        if ($editorClass < (int) $newForum->minclasswrite) {
            throw new ForumReplyException('You do not have permission to move topics into that forum.');
        }

        $postCount = (int) Post::query()->where('topicid', $topic->id)->count();

        DB::transaction(function () use ($topic, $oldForum, $newForum, $postCount) {
            Topic::query()->where('id', $topic->id)->update(['forumid' => $newForum->id]);
            Forum::query()->where('id', $oldForum->id)->update([
                'topiccount' => DB::raw('GREATEST(topiccount - 1, 0)'),
                'postcount' => DB::raw('GREATEST(postcount - '.$postCount.', 0)'),
            ]);
            Forum::query()->where('id', $newForum->id)->update([
                'topiccount' => DB::raw('topiccount + 1'),
                'postcount' => DB::raw('postcount + '.$postCount),
            ]);
        });

        $this->bustForumLastReplied((int) $oldForum->id);
        $this->bustForumLastReplied((int) $newForum->id);

        return $topic->fresh() ?? $topic;
    }

    /**
     * Return the edit history for a post, newest first. Visible to
     * anyone who can view the post — the snapshots are pre-edit
     * bodies that were already public at the moment of the edit.
     *
     * @return Collection<int, PostEdit>
     */
    public function getPostHistory(int $postId): Collection
    {
        return PostEdit::query()
            ->with('editor:id,username,class')
            ->where('postid', $postId)
            ->orderByDesc('edited_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Public predicate: can $userId moderate $topicId (sticky / lock /
     * color / move)? True for forum moderators and postmanage holders.
     */
    public function canModerateTopic(int $topicId, int $userId): bool
    {
        $topic = Topic::query()->find($topicId);
        if (! $topic) {
            return false;
        }
        $user = User::query()->find($userId);
        if (! $user) {
            return false;
        }

        return $this->isForumModerator((int) $topic->forumid, (int) $user->id)
            || $this->userCan($user, 'postmanage');
    }

    /**
     * @return array{0: Topic, 1: Forum}
     *
     * @throws ForumReplyException
     */
    private function ensureTopicMod(int $topicId, int $editorId): array
    {
        $topic = Topic::query()->find($topicId);
        if (! $topic) {
            throw new ForumReplyException('Unknown topic.');
        }
        $forum = Forum::query()->find((int) $topic->forumid);
        if (! $forum) {
            throw new ForumReplyException('Unknown forum.');
        }
        $editor = User::query()->find($editorId);
        if (! $editor) {
            throw new ForumReplyException('Unknown user.');
        }
        $isMod = $this->isForumModerator((int) $forum->id, (int) $editor->id);
        $canManage = $this->userCan($editor, 'postmanage');
        if (! $isMod && ! $canManage) {
            throw new ForumReplyException('You do not have permission to moderate this topic.');
        }

        return [$topic, $forum];
    }

    private function bustForumLastReplied(int $forumId): void
    {
        try {
            Cache::forget('forum_'.$forumId.'_last_replied_topic_content');
            Cache::forget('forums_list');
        } catch (\Throwable) {
            // Cache backend can be unreachable; ignore.
        }
    }

    /**
     * Public permission check: can $userId edit $postId? Used by Livewire
     * UI to decide whether to render an Edit button. Returns true for
     * the post author (when topic is unlocked), forum moderators, and
     * users with postmanage permission.
     */
    public function canEditPost(int $postId, int $userId): bool
    {
        $post = Post::query()->find($postId);
        if (! $post) {
            return false;
        }
        $topic = Topic::query()->find((int) $post->topicid);
        if (! $topic) {
            return false;
        }
        $user = User::query()->find($userId);
        if (! $user) {
            return false;
        }
        $isAuthor = (int) $post->userid === (int) $user->id;
        $isMod = $this->isForumModerator((int) $topic->forumid, (int) $user->id);
        $canManage = $this->userCan($user, 'postmanage');
        $locked = (string) $topic->locked === 'yes';

        if ($isMod || $canManage) {
            return true;
        }

        return $isAuthor && ! $locked;
    }

    /**
     * Public permission check: can $userId delete $postId? Only forum
     * moderators / postmanage; never the first post of a topic.
     */
    public function canDeletePost(int $postId, int $userId): bool
    {
        $post = Post::query()->find($postId);
        if (! $post) {
            return false;
        }
        $topic = Topic::query()->find((int) $post->topicid);
        if (! $topic) {
            return false;
        }
        $user = User::query()->find($userId);
        if (! $user) {
            return false;
        }
        $isMod = $this->isForumModerator((int) $topic->forumid, (int) $user->id);
        $canManage = $this->userCan($user, 'postmanage');
        if (! $isMod && ! $canManage) {
            return false;
        }
        $firstPostId = (int) Post::query()->where('topicid', $topic->id)->min('id');

        return $firstPostId !== (int) $post->id;
    }

    /**
     * Whether $userId is an entry in forummods for $forumId. Mirrors the
     * legacy is_forum_moderator() helper but takes the user id explicitly
     * so we don't depend on the IN_NEXUS $CURUSER global.
     */
    private function isForumModerator(int $forumId, int $userId): bool
    {
        if ($forumId <= 0 || $userId <= 0) {
            return false;
        }

        return DB::table('forummods')
            ->where('forumid', $forumId)
            ->where('userid', $userId)
            ->exists();
    }

    /**
     * Bridge to the global user_can() helper (postmanage etc). Returns
     * false when the helper isn't loaded (e.g. during unit tests that
     * boot a slimmed kernel) so behaviour degrades safely.
     */
    private function userCan(User $user, string $permission): bool
    {
        if (! function_exists('user_can')) {
            return false;
        }

        return (bool) user_can($permission, false, (int) $user->id);
    }

    private function revokeBonus(int $userId): void
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
                'seedbonus' => DB::raw('GREATEST(seedbonus - '.$points.', 0)'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[forum] makepost bonus revoke failed: '.$e->getMessage());
        }
    }

    private function notifyEditedAuthor(Topic $topic, Post $post, User $editor): void
    {
        $authorId = (int) ($post->userid ?? 0);
        if ($authorId <= 0 || $authorId === (int) $editor->id) {
            return;
        }
        $author = User::query()->find($authorId);
        if (! $author) {
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
                    ? nexus_trans('forum.post.edited_notify_subject', [], $locale)
                    : 'Your post has been edited',
                'msg' => function_exists('nexus_trans')
                    ? nexus_trans('forum.post.edited_notify_body', ['topic_subject' => $postUrl, 'editor' => $editor->username ?? '#'.$editor->id], $locale)
                    : 'Your post has been edited by '.($editor->username ?? '#'.$editor->id).': '.$postUrl,
                'added' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[forum] post edit PM notify failed: '.$e->getMessage());
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
