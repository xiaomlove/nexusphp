<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class UserHistoryControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/userhistory.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/userhistory.php?action=viewposts&id=1');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_missing_id_returns_422(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/userhistory.php?action=viewposts')->assertStatus(422);
    }

    public function test_invalid_id_returns_422(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/userhistory.php?action=viewposts&id=0')->assertStatus(422);
        $this->get('/userhistory.php?action=viewposts&id=-1')->assertStatus(422);
    }

    public function test_unknown_id_returns_404(): void
    {
        $user = $this->createUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/userhistory.php?action=viewposts&id=999999999')
            ->assertNotFound();
    }

    public function test_parked_viewer_is_forbidden(): void
    {
        $viewer = $this->createUser();
        NexusDB::table('users')->where('id', $viewer->id)->update(['parked' => 'yes']);
        $viewer->refresh();
        $this->actingAs($viewer, 'nexus-web');

        $this->get('/userhistory.php?action=viewposts&id='.$viewer->id)
            ->assertForbidden();
    }

    public function test_user_without_viewhistory_cannot_view_other_user_history(): void
    {
        $viewer = $this->createUser(['class' => User::CLASS_USER]);
        $other = $this->createUser();
        $this->actingAs($viewer, 'nexus-web');

        $this->get('/userhistory.php?action=viewposts&id='.$other->id)
            ->assertForbidden();
    }

    public function test_missing_action_returns_422(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/userhistory.php?id='.$user->id)->assertStatus(422);
    }

    public function test_unknown_action_returns_422(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/userhistory.php?id='.$user->id.'&action=viewsnatches')
            ->assertStatus(422);
    }

    public function test_viewposts_with_no_posts_renders_nothing_found(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/userhistory.php?action=viewposts&id='.$user->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Posts history', $body);
        $this->assertStringContainsString('Nothing found.', $body);
    }

    public function test_viewposts_renders_user_post(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $forumId = $this->createForum(0);
        $topicId = $this->createTopic($forumId, (int) $user->id, 'My Topic Subject');
        $this->createPost($topicId, (int) $user->id, 'hello world body');

        $response = $this->get('/userhistory.php?action=viewposts&id='.$user->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('My Topic Subject', $body);
        $this->assertStringContainsString('hello world body', $body);
    }

    public function test_viewposts_hides_posts_above_viewer_class(): void
    {
        $viewer = $this->createUser(['class' => User::CLASS_USER]);
        $this->actingAs($viewer, 'nexus-web');

        $forumId = $this->createForum(User::CLASS_MODERATOR);
        $topicId = $this->createTopic($forumId, (int) $viewer->id, 'Mod Topic');
        $this->createPost($topicId, (int) $viewer->id, 'mod-only body');

        $response = $this->get('/userhistory.php?action=viewposts&id='.$viewer->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Nothing found.', $body);
        $this->assertStringNotContainsString('mod-only body', $body);
    }

    public function test_viewcomments_with_no_comments_renders_nothing_found(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/userhistory.php?action=viewcomments&id='.$user->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Comments history', $body);
        $this->assertStringContainsString('Nothing found.', $body);
    }

    public function test_viewcomments_renders_user_comment(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $this->createComment((int) $user->id, 0, 'comment text body');

        $response = $this->get('/userhistory.php?action=viewcomments&id='.$user->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('comment text body', $body);
    }

    public function test_html_in_topic_subject_is_escaped_in_viewposts(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $forumId = $this->createForum(0);
        $topicId = $this->createTopic($forumId, (int) $user->id, '<script>alert(1)</script>');
        $this->createPost($topicId, (int) $user->id, 'b');

        $response = $this->get('/userhistory.php?action=viewposts&id='.$user->id);

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function test_pagination_links_appear_when_total_exceeds_page_size(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $forumId = $this->createForum(0);
        $topicId = $this->createTopic($forumId, (int) $user->id, 'paginate');
        for ($i = 0; $i < 16; $i++) {
            $this->createPost($topicId, (int) $user->id, 'p'.$i);
        }

        $response = $this->get('/userhistory.php?action=viewposts&id='.$user->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('page=1', $body);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(
                ['lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );
    }

    private function createForum(int $minclassread): int
    {
        return (int) NexusDB::table('forums')->insertGetId([
            'name' => 'UHC-'.bin2hex(random_bytes(2)),
            'description' => '',
            'minclassread' => $minclassread,
            'minclasswrite' => 0,
            'minclasscreate' => 0,
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
