<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/increment-bulk.php` (GET form) and
 * `/take-increment-bulk.php` (POST handler) contracts.
 *
 * Sysop-only bulk increment tool. The form renders a type selector,
 * amount input, class checkboxes, and message fields. The handler
 * increments the selected column for matching users and sends PMs.
 */
class IncrementBulkControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/increment-bulk.php';
    }

    // ------------------------------------------------------------------
    // GET /increment-bulk.php — auth / permission gate
    // ------------------------------------------------------------------

    public function test_guest_form_request_redirects_to_login(): void
    {
        $response = $this->get('/increment-bulk.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_below_sysop_user_is_forbidden_on_form(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/increment-bulk.php')->assertForbidden();
    }

    public function test_sysop_can_access_form(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/increment-bulk.php');

        $response->assertOk();
        $this->assertStringContainsString('take-increment-bulk.php', (string) $response->getContent());
        $this->assertStringContainsString('name="type"', (string) $response->getContent());
        $this->assertStringContainsString('name="amount"', (string) $response->getContent());
    }

    public function test_form_shows_success_banner_on_sent_param(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/increment-bulk.php?sent=1&type=seedbonus');

        $response->assertOk();
        $this->assertStringContainsString('has been added', (string) $response->getContent());
    }

    // ------------------------------------------------------------------
    // POST /take-increment-bulk.php — auth / permission gate
    // ------------------------------------------------------------------

    public function test_guest_post_redirects_to_login(): void
    {
        $response = $this->post('/take-increment-bulk.php', [
            'type' => 'seedbonus',
            'amount' => '10',
            'msg' => 'test',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_below_sysop_user_is_forbidden_on_post(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->post('/take-increment-bulk.php', [
            'type' => 'seedbonus',
            'amount' => '10',
            'msg' => 'test',
            'classes' => [(string) User::CLASS_USER],
        ])->assertForbidden();
    }

    // ------------------------------------------------------------------
    // POST /take-increment-bulk.php — validation
    // ------------------------------------------------------------------

    public function test_missing_msg_returns_error(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/take-increment-bulk.php', [
            'type' => 'seedbonus',
            'amount' => '10',
            'msg' => '',
            'classes' => [(string) User::CLASS_USER],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('blank', (string) $response->getContent());
    }

    public function test_missing_amount_returns_error(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/take-increment-bulk.php', [
            'type' => 'seedbonus',
            'amount' => '',
            'msg' => 'hello',
            'classes' => [(string) User::CLASS_USER],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('blank', (string) $response->getContent());
    }

    public function test_non_numeric_amount_returns_error(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/take-increment-bulk.php', [
            'type' => 'seedbonus',
            'amount' => 'abc',
            'msg' => 'hello',
            'classes' => [(string) User::CLASS_USER],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('numeric', (string) $response->getContent());
    }

    public function test_invalid_type_returns_error(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/take-increment-bulk.php', [
            'type' => 'fake_type',
            'amount' => '10',
            'msg' => 'hello',
            'classes' => [(string) User::CLASS_USER],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Invalid type', (string) $response->getContent());
    }

    public function test_no_classes_selected_returns_no_valid_filter_error(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/take-increment-bulk.php', [
            'type' => 'seedbonus',
            'amount' => '10',
            'msg' => 'hello',
            'subject' => 'bonus',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('No valid filter', (string) $response->getContent());
    }

    public function test_tmp_invites_without_duration_returns_error(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/take-increment-bulk.php', [
            'type' => 'tmp_invites',
            'amount' => '2',
            'msg' => 'hello',
            'subject' => 'invites',
            'classes' => [(string) User::CLASS_USER],
            'duration' => '0',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Invalid duration', (string) $response->getContent());
    }

    // ------------------------------------------------------------------
    // POST /take-increment-bulk.php — happy path (seedbonus)
    // ------------------------------------------------------------------

    public function test_happy_path_increments_seedbonus_and_sends_pm(): void
    {
        $sysop = $this->createTestUser([
            'class' => User::CLASS_SYSOP,
            'username' => 'sysop_bulk_'.bin2hex(random_bytes(3)),
        ]);
        $this->actingAs($sysop, 'nexus-web');

        // Create a target user matching the class filter.
        $target = $this->createTestUser([
            'class' => User::CLASS_USER,
            'seedbonus' => '100.0',
            'enabled' => 'yes',
            'status' => 'confirmed',
        ]);

        $beforeBonus = (float) NexusDB::table('users')
            ->where('id', $target->id)
            ->value('seedbonus');

        $response = $this->post('/take-increment-bulk.php', [
            'type' => 'seedbonus',
            'amount' => '50',
            'msg' => 'Bonus reward for all!',
            'subject' => 'Bonus Added',
            'sender' => 'self',
            'classes' => [(string) User::CLASS_USER],
        ]);

        $response->assertRedirect('/increment-bulk.php?sent=1&type=seedbonus');

        // Verify seedbonus was incremented.
        $afterBonus = (float) NexusDB::table('users')
            ->where('id', $target->id)
            ->value('seedbonus');
        $this->assertEqualsWithDelta($beforeBonus + 50.0, $afterBonus, 0.5);

        // Verify PM was sent.
        $this->assertDatabaseHas('messages', [
            'receiver' => $target->id,
            'subject' => 'Bonus Added',
        ]);
    }

    public function test_system_sender_sets_sender_id_to_zero(): void
    {
        $sysop = $this->createTestUser([
            'class' => User::CLASS_SYSOP,
            'username' => 'sysop_sys_'.bin2hex(random_bytes(3)),
        ]);
        $this->actingAs($sysop, 'nexus-web');

        $target = $this->createTestUser([
            'class' => User::CLASS_USER,
            'seedbonus' => '10.0',
            'enabled' => 'yes',
            'status' => 'confirmed',
        ]);

        $response = $this->post('/take-increment-bulk.php', [
            'type' => 'seedbonus',
            'amount' => '5',
            'msg' => 'System bonus',
            'subject' => 'System',
            'sender' => 'system',
            'classes' => [(string) User::CLASS_USER],
        ]);

        $response->assertRedirect();

        // Verify sender is 0 (system).
        $pm = NexusDB::table('messages')
            ->where('receiver', $target->id)
            ->where('subject', 'System')
            ->first();
        $this->assertNotNull($pm);
        $this->assertSame(0, (int) $pm->sender);
    }

    // ------------------------------------------------------------------
    // Helper methods
    // ------------------------------------------------------------------

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
