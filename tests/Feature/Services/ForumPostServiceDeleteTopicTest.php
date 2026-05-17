<?php

namespace Tests\Feature\Services;

use App\Models\User;
use App\Services\Exceptions\ForumReplyException;
use App\Services\ForumPostService;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class ForumPostServiceDeleteTopicTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    public function test_moderator_can_delete_topic_and_cascade(): void
    {
        $mod = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $author = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $forumId = $this->createForum();
        $this->makeForumModerator($forumId, (int) $mod->id);

        $topicId = $this->createTopic($forumId, (int) $author->id, 'About to be deleted');
        $firstPostId = $this->createPost($topicId, (int) $author->id, 'first body');
        $secondPostId = $this->createPost($topicId, (int) $author->id, 'second body');
        NexusDB::table('topics')->where('id', $topicId)->update(['firstpost' => $firstPostId]);
        NexusDB::table('readposts')->insert([
            ['userid' => (int) $author->id, 'topicid' => $topicId, 'lastpostread' => $secondPostId],
        ]);
        NexusDB::table('forums')->where('id', $forumId)->update([
            'topiccount' => 1,
            'postcount' => 2,
        ]);

        $service = app(ForumPostService::class);
        $result = $service->deleteTopic($topicId, (int) $mod->id);

        $this->assertSame($forumId, $result['forumId']);
        $this->assertSame((int) $author->id, $result['authorId']);
        $this->assertSame(2, $result['postCount']);

        $this->assertSame(0, NexusDB::table('topics')->where('id', $topicId)->count());
        $this->assertSame(0, NexusDB::table('posts')->where('topicid', $topicId)->count());
        $this->assertSame(0, NexusDB::table('readposts')->where('topicid', $topicId)->count());

        $forumRow = (array) NexusDB::table('forums')->where('id', $forumId)->first();
        $this->assertSame(0, (int) $forumRow['topiccount']);
        $this->assertSame(0, (int) $forumRow['postcount']);
    }

    public function test_non_moderator_cannot_delete_topic(): void
    {
        $randomUser = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $author = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $forumId = $this->createForum();
        $topicId = $this->createTopic($forumId, (int) $author->id, 'survives');
        $firstPostId = $this->createPost($topicId, (int) $author->id, 'first body');
        NexusDB::table('topics')->where('id', $topicId)->update(['firstpost' => $firstPostId]);

        $service = app(ForumPostService::class);

        $this->expectException(ForumReplyException::class);
        try {
            $service->deleteTopic($topicId, (int) $randomUser->id);
        } finally {
            $this->assertSame(1, NexusDB::table('topics')->where('id', $topicId)->count());
            $this->assertSame(1, NexusDB::table('posts')->where('topicid', $topicId)->count());
        }
    }

    public function test_unknown_topic_throws(): void
    {
        $mod = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $service = app(ForumPostService::class);

        $this->expectException(ForumReplyException::class);
        $service->deleteTopic(999999999, (int) $mod->id);
    }

    private function createForum(): int
    {
        return (int) NexusDB::table('forums')->insertGetId([
            'name' => 'DeleteTopicTest-'.bin2hex(random_bytes(2)),
            'description' => '',
            'minclassread' => 0,
            'minclasswrite' => 0,
            'minclasscreate' => 0,
        ]);
    }

    private function makeForumModerator(int $forumId, int $userId): void
    {
        NexusDB::table('forummods')->insert([
            'forumid' => $forumId,
            'userid' => $userId,
        ]);
    }

    private function createTopic(int $forumId, int $userId, string $subject): int
    {
        return (int) NexusDB::table('topics')->insertGetId([
            'forumid' => $forumId,
            'subject' => $subject,
            'userid' => $userId,
            'locked' => 'no',
            'firstpost' => 0,
            'lastpost' => 0,
            'sticky' => 'no',
            'hlcolor' => 0,
            'views' => 0,
        ]);
    }

    private function createPost(int $topicId, int $userId, string $body): int
    {
        return (int) NexusDB::table('posts')->insertGetId([
            'topicid' => $topicId,
            'userid' => $userId,
            'added' => Carbon::now()->toDateTimeString(),
            'body' => $body,
            'ori_body' => '',
            'editedby' => 0,
        ]);
    }
}
