<?php

namespace Tests\Feature\Legacy;

use App\Events\TorrentPromotionChanged;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/freeleech.php` contract.
 */
class FreeleechControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/freeleech.php';
        Event::fake([TorrentPromotionChanged::class]);
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/freeleech.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location')
        );
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/freeleech.php')->assertForbidden();
        $this->get('/freeleech.php?action=setallfree')->assertForbidden();
    }

    public function test_admin_get_default_renders_menu(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/freeleech.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        // The legacy admin UI links to each action by name; pin the
        // exact action names so the menu stays addressable.
        $this->assertStringContainsString('action=setallfree', $body);
        $this->assertStringContainsString('action=setall2up', $body);
        $this->assertStringContainsString('action=setall2up_free', $body);
        $this->assertStringContainsString('action=setallhalf_down', $body);
        $this->assertStringContainsString('action=setall2up_half_down', $body);
        $this->assertStringContainsString('action=setallnormal', $body);

        Event::assertNotDispatched(TorrentPromotionChanged::class);
    }

    public function test_setallfree_updates_global_state_and_dispatches_event(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        // Reset to known state — the migration / seed may have left
        // an arbitrary value in place; we assert the *post-call*
        // value, but pinning the starting value makes the diff
        // obvious if the test ever fails.
        NexusDB::table('torrents_state')->update(['global_sp_state' => 1]);

        $response = $this->get('/freeleech.php?action=setallfree');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('All torrents have been set free', $body);

        $state = (int) NexusDB::table('torrents_state')->value('global_sp_state');
        $this->assertSame(2, $state);

        Event::assertDispatched(
            TorrentPromotionChanged::class,
            fn (TorrentPromotionChanged $e) => $e->torrentId === 0
                && $e->spState === 2
                && $e->global === true,
        );
    }

    public function test_setallnormal_resets_global_state(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        NexusDB::table('torrents_state')->update(['global_sp_state' => 2]);

        $this->post('/freeleech.php', ['action' => 'setallnormal'])->assertOk();

        $state = (int) NexusDB::table('torrents_state')->value('global_sp_state');
        $this->assertSame(1, $state);
    }

    public function test_unknown_action_falls_back_to_menu(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/freeleech.php?action=bogus');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('action=setallfree', $body);
        Event::assertNotDispatched(TorrentPromotionChanged::class);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(
                ['lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );
    }
}
