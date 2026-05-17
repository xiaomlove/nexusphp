<?php

namespace Tests\Feature\Livewire;

use App\Livewire\ForumSearch;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class ForumSearchTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_URI'] = '/forum/search';
    }

    public function test_empty_keywords_show_the_initial_prompt_and_no_results(): void
    {
        $user = $this->createUser();

        Livewire::actingAs($user, 'nexus-web')
            ->test(ForumSearch::class)
            ->assertSee('Type a keyword above')
            ->assertDontSee('matching post');
    }

    public function test_matches_topic_subject_on_the_first_post(): void
    {
        $user = $this->createUser();
        $forumId = $this->createOpenForum();
        $topicId = $this->createTopic($forumId, $user->id, 'My very specific findable subject');
        $firstPostId = $this->createPost($topicId, $user->id, 'body unrelated');
        NexusDB::table('topics')->where('id', $topicId)->update(['firstpost' => $firstPostId]);

        Livewire::actingAs($user, 'nexus-web')
            ->test(ForumSearch::class, ['keywords' => 'findable'])
            ->assertSee('My very specific findable subject');
    }

    public function test_matches_post_body_on_a_subsequent_reply(): void
    {
        $user = $this->createUser();
        $forumId = $this->createOpenForum();
        $topicId = $this->createTopic($forumId, $user->id, 'Topic title without the needle');
        $firstPostId = $this->createPost($topicId, $user->id, 'first post body');
        $this->createPost($topicId, $user->id, 'a reply that contains the needle-word here');
        NexusDB::table('topics')->where('id', $topicId)->update(['firstpost' => $firstPostId]);

        Livewire::actingAs($user, 'nexus-web')
            ->test(ForumSearch::class, ['keywords' => 'needle-word'])
            ->assertSee('Topic title without the needle');
    }

    public function test_does_not_return_posts_from_forums_above_the_user_class(): void
    {
        $user = $this->createUser(['class' => User::CLASS_USER]);
        $restrictedForumId = (int) NexusDB::table('forums')->insertGetId([
            'name' => 'Mod only-'.bin2hex(random_bytes(2)),
            'description' => '',
            'minclassread' => User::CLASS_MODERATOR,
            'minclasswrite' => User::CLASS_MODERATOR,
            'minclasscreate' => User::CLASS_MODERATOR,
        ]);
        $topicId = $this->createTopic($restrictedForumId, $user->id, 'Mod secret marker-1234');
        $firstPostId = $this->createPost($topicId, $user->id, 'mod-only body');
        NexusDB::table('topics')->where('id', $topicId)->update(['firstpost' => $firstPostId]);

        Livewire::actingAs($user, 'nexus-web')
            ->test(ForumSearch::class, ['keywords' => 'marker-1234'])
            ->assertSee('No posts matched your search');
    }

    public function test_clear_resets_keywords_and_pagination(): void
    {
        $user = $this->createUser();

        $component = Livewire::actingAs($user, 'nexus-web')
            ->test(ForumSearch::class, ['keywords' => 'something'])
            ->call('clear');

        $this->assertSame('', $component->get('keywords'));
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createUser(array $overrides = []): User
    {
        return $this->createLegacyUser(overrides: array_merge([
            'lang' => self::ENGLISH_LANGUAGE_ID,
            'class' => User::CLASS_USER,
        ], $overrides));
    }

    private function createOpenForum(): int
    {
        return (int) NexusDB::table('forums')->insertGetId([
            'name' => 'SearchTestForum-'.bin2hex(random_bytes(2)),
            'description' => '',
            'minclassread' => 0,
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
}
