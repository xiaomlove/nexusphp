<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/takemessage.php` contract.
 *
 * POST-only handler for the "Send PM" / "Reply" / "Forward"
 * forms. Validates input, enforces anti-flood + recipient privacy,
 * INSERTs into `messages`, optionally fans out an e-mail.
 */
class TakeMessageControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/takemessage.php';
    }

    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(['lang' => self::ENGLISH_LANGUAGE_ID], $overrides),
        );
    }

    public function test_guest_post_redirects_to_login(): void
    {
        $response = $this->post('/takemessage.php', [
            'receiver' => 1,
            'body' => 'hi',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_get_returns_405(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/takemessage.php')->assertStatus(405);
    }

    public function test_missing_receiver_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $this->post('/takemessage.php', [
            'body' => 'hi',
        ])->assertStatus(422);
    }

    public function test_empty_body_returns_422(): void
    {
        $sender = $this->createTestUser();
        $receiver = $this->createTestUser();
        $this->actingAs($sender, 'nexus-web');

        $this->post('/takemessage.php', [
            'receiver' => $receiver->id,
            'body' => '   ',
        ])->assertStatus(422);
    }

    public function test_unknown_recipient_returns_422(): void
    {
        $sender = $this->createTestUser();
        $this->actingAs($sender, 'nexus-web');

        $this->post('/takemessage.php', [
            'receiver' => 99999999,
            'body' => 'hi',
        ])->assertStatus(422);
    }
}
