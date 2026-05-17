<?php

namespace Tests\Feature\Livewire;

use App\Livewire\ForumUnread;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class ForumUnreadTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    private int $forumId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/forum/unread';

        $this->forumId = (int) NexusDB::table('forums')->insertGetId([
            'name' => 'UnreadTestForum-'.bin2hex(random_bytes(2)),
            'description' => '',
            'minclassread' => 0,
            'minclasswrite' => 0,
            'minclasscreate' => 0,
        ]);
    }

    public function test_catch_up_clears_readposts_for_the_current_user_only(): void
    {
        $alice = $this->createUser();
        $bob = $this->createUser();
        $topicId = $this->createTopic('alice-and-bob-readposts');

        NexusDB::table('readposts')->insert([
            ['userid' => (int) $alice->id, 'topicid' => $topicId, 'lastpostread' => 11],
            ['userid' => (int) $bob->id, 'topicid' => $topicId, 'lastpostread' => 22],
        ]);

        Livewire::actingAs($alice, 'nexus-web')
            ->test(ForumUnread::class)
            ->call('catchUp');

        $this->assertSame(0, NexusDB::table('readposts')->where('userid', $alice->id)->count());
        $this->assertSame(1, NexusDB::table('readposts')->where('userid', $bob->id)->count());
    }

    public function test_catch_up_pins_last_catchup_to_the_current_max_post_id(): void
    {
        $user = $this->createUser();
        $topicId = $this->createTopic('last-catchup-fixture');

        $this->createPost($topicId, (int) $user->id, '2024-01-01 00:00:00');
        $latestPostId = $this->createPost($topicId, (int) $user->id, '2024-01-02 00:00:00');

        Livewire::actingAs($user, 'nexus-web')
            ->test(ForumUnread::class)
            ->call('catchUp');

        $this->assertSame(
            $latestPostId,
            (int) NexusDB::table('users')->where('id', $user->id)->value('last_catchup'),
        );
    }

    public function test_catch_up_forgets_the_legacy_readposts_cache_key(): void
    {
        $user = $this->createUser();
        $cacheKey = 'user_'.$user->id.'_last_read_post_list';

        Cache::put($cacheKey, 'no record', 900);
        $this->assertSame('no record', Cache::get($cacheKey));

        Livewire::actingAs($user, 'nexus-web')
            ->test(ForumUnread::class)
            ->call('catchUp');

        $this->assertNull(Cache::get($cacheKey));
    }

    public function test_catch_up_is_a_noop_for_a_guest(): void
    {
        $sentinelTopicId = $this->createTopic('guest-noop-sentinel');
        NexusDB::table('readposts')->insert([
            'userid' => 999_999,
            'topicid' => $sentinelTopicId,
            'lastpostread' => 1,
        ]);
        $before = NexusDB::table('readposts')->count();

        Livewire::test(ForumUnread::class)->call('catchUp');

        $this->assertSame($before, NexusDB::table('readposts')->count());
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge([
                'lang' => self::ENGLISH_LANGUAGE_ID,
                'class' => User::CLASS_USER,
            ], $overrides),
        );
    }

    private function createTopic(string $subject): int
    {
        return (int) NexusDB::table('topics')->insertGetId([
            'forumid' => $this->forumId,
            'subject' => $subject,
            'userid' => 1,
            'locked' => 'no',
            'firstpost' => 0,
            'lastpost' => 0,
            'sticky' => 'no',
            'hlcolor' => 0,
            'views' => 0,
        ]);
    }

    private function createPost(int $topicId, int $userId, string $added): int
    {
        return (int) NexusDB::table('posts')->insertGetId([
            'topicid' => $topicId,
            'userid' => $userId,
            'added' => Carbon::parse($added)->toDateTimeString(),
            'body' => 'body-'.bin2hex(random_bytes(2)),
            'ori_body' => '',
            'editedby' => 0,
        ]);
    }
}
