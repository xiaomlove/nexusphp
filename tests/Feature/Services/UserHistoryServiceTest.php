<?php

namespace Tests\Feature\Services;

use App\Models\User;
use App\Services\UserHistoryPage;
use App\Services\UserHistoryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class UserHistoryServiceTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    public function test_total_pages_rounds_up(): void
    {
        $page = new UserHistoryPage(total: 31, page: 0, perPage: 15, rows: new Collection);

        $this->assertSame(3, $page->totalPages());
    }

    public function test_total_pages_is_one_for_a_partial_page(): void
    {
        $page = new UserHistoryPage(total: 7, page: 0, perPage: 15, rows: new Collection);

        $this->assertSame(1, $page->totalPages());
    }

    public function test_total_pages_is_zero_when_empty(): void
    {
        $page = new UserHistoryPage(total: 0, page: 0, perPage: 15, rows: new Collection);

        $this->assertSame(0, $page->totalPages());
    }

    public function test_find_user_returns_null_for_invalid_uid(): void
    {
        $service = app(UserHistoryService::class);

        $this->assertNull($service->findUser(0));
        $this->assertNull($service->findUser(-1));
    }

    public function test_find_user_returns_user_for_existing_uid(): void
    {
        $service = app(UserHistoryService::class);
        $user = $this->createLegacyUser();

        $found = $service->findUser((int) $user->id);

        $this->assertNotNull($found);
        $this->assertSame((int) $user->id, (int) $found->id);
    }

    public function test_is_parked_reads_users_parked_column(): void
    {
        $service = app(UserHistoryService::class);
        $user = $this->createLegacyUser();

        NexusDB::table('users')->where('id', $user->id)->update(['parked' => 'no']);
        $fresh = $service->findUser((int) $user->id);
        $this->assertNotNull($fresh);
        $this->assertFalse($service->isParked($fresh));

        NexusDB::table('users')->where('id', $user->id)->update(['parked' => 'yes']);
        $fresh = $service->findUser((int) $user->id);
        $this->assertNotNull($fresh);
        $this->assertTrue($service->isParked($fresh));
    }

    public function test_viewposts_returns_empty_page_when_user_has_no_posts(): void
    {
        $service = app(UserHistoryService::class);
        $user = $this->createLegacyUser();

        $result = $service->viewposts(uid: (int) $user->id, viewerClass: 255);

        $this->assertSame(0, $result->total);
        $this->assertSame(0, $this->countRows($result));
    }

    public function test_viewposts_returns_user_posts(): void
    {
        $service = app(UserHistoryService::class);
        $author = $this->createLegacyUser();
        $forumId = $this->createForum(minclassread: 0);
        $topicId = $this->createTopic($forumId, (int) $author->id);
        $postId = $this->createPost($topicId, (int) $author->id, 'first body');

        $result = $service->viewposts(uid: (int) $author->id, viewerClass: 255);

        $this->assertSame(1, $result->total);
        $rows = $this->toArray($result);
        $this->assertCount(1, $rows);
        $this->assertSame($postId, (int) $rows[0]->id);
        $this->assertSame('first body', $rows[0]->body);
    }

    public function test_viewposts_hides_posts_in_forums_above_viewer_class(): void
    {
        $service = app(UserHistoryService::class);
        $author = $this->createLegacyUser();
        $forumId = $this->createForum(minclassread: User::CLASS_MODERATOR);
        $topicId = $this->createTopic($forumId, (int) $author->id);
        $this->createPost($topicId, (int) $author->id, 'mod-only body');

        $resultLow = $service->viewposts(
            uid: (int) $author->id,
            viewerClass: User::CLASS_USER,
        );
        $resultHigh = $service->viewposts(
            uid: (int) $author->id,
            viewerClass: User::CLASS_MODERATOR,
        );

        $this->assertSame(0, $resultLow->total);
        $this->assertSame(1, $resultHigh->total);
    }

    public function test_viewposts_pagination_slices_results(): void
    {
        $service = app(UserHistoryService::class);
        $author = $this->createLegacyUser();
        $forumId = $this->createForum(minclassread: 0);
        $topicId = $this->createTopic($forumId, (int) $author->id);

        for ($i = 0; $i < 4; $i++) {
            $this->createPost($topicId, (int) $author->id, 'body '.$i);
        }

        $resultFirst = $service->viewposts(
            uid: (int) $author->id,
            viewerClass: 255,
            page: 0,
            perPage: 2,
        );
        $resultSecond = $service->viewposts(
            uid: (int) $author->id,
            viewerClass: 255,
            page: 1,
            perPage: 2,
        );

        $this->assertSame(4, $resultFirst->total);
        $this->assertCount(2, $this->toArray($resultFirst));
        $this->assertCount(2, $this->toArray($resultSecond));
        $this->assertSame(2, $resultFirst->totalPages());
    }

    public function test_viewcomments_returns_empty_page_when_user_has_no_comments(): void
    {
        $service = app(UserHistoryService::class);
        $user = $this->createLegacyUser();

        $result = $service->viewcomments(uid: (int) $user->id);

        $this->assertSame(0, $result->total);
    }

    public function test_viewcomments_returns_user_comments(): void
    {
        $service = app(UserHistoryService::class);
        $author = $this->createLegacyUser();
        $commentId = $this->createComment((int) $author->id, 0, 'a comment');

        $result = $service->viewcomments(uid: (int) $author->id);

        $this->assertSame(1, $result->total);
        $rows = $this->toArray($result);
        $this->assertCount(1, $rows);
        $this->assertSame($commentId, (int) $rows[0]->id);
        $this->assertSame('a comment', $rows[0]->text);
    }

    public function test_editor_username_returns_username_or_null(): void
    {
        $service = app(UserHistoryService::class);
        $editor = $this->createLegacyUser();

        $this->assertSame($editor->username, $service->editorUsername((int) $editor->id));
        $this->assertNull($service->editorUsername(0));
        $this->assertNull($service->editorUsername(999999999));
    }

    public function test_comment_page_on_details_counts_earlier_comments(): void
    {
        $service = app(UserHistoryService::class);
        $author = $this->createLegacyUser();
        $torrentId = 12345;

        $ids = [];
        for ($i = 0; $i < 21; $i++) {
            $ids[] = $this->createComment((int) $author->id, $torrentId, 'c'.$i);
        }
        $targetId = end($ids);

        $this->assertSame(1, $service->commentPageOnDetails($torrentId, $targetId, 20));
    }

    /**
     * @return array<int, object>
     */
    private function toArray(UserHistoryPage $page): array
    {
        $rows = $page->rows;
        if ($rows instanceof Collection) {
            return $rows->values()->all();
        }
        if (is_array($rows)) {
            return array_values($rows);
        }

        return array_values(iterator_to_array($rows, false));
    }

    private function countRows(UserHistoryPage $page): int
    {
        return count($this->toArray($page));
    }

    private function createForum(int $minclassread = 0): int
    {
        return (int) NexusDB::table('forums')->insertGetId([
            'name' => 'UserHistoryTest-'.bin2hex(random_bytes(2)),
            'description' => '',
            'minclassread' => $minclassread,
            'minclasswrite' => 0,
            'minclasscreate' => 0,
        ]);
    }

    private function createTopic(int $forumId, int $userId): int
    {
        return (int) NexusDB::table('topics')->insertGetId([
            'forumid' => $forumId,
            'subject' => 'UH-'.bin2hex(random_bytes(2)),
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

    private function createComment(int $userId, int $torrentId, string $text): int
    {
        return (int) NexusDB::table('comments')->insertGetId([
            'user' => $userId,
            'torrent' => $torrentId,
            'added' => Carbon::now()->toDateTimeString(),
            'text' => $text,
            'ori_text' => '',
            'editedby' => 0,
        ]);
    }
}
