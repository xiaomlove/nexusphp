<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/massmail.php` contract (GET form + POST handler).
 *
 * Sysop-only mass email tool. GET renders the form; POST sends emails
 * to all users matching the class filter.
 */
class MassMailControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/massmail.php';
    }

    // ------------------------------------------------------------------
    // Auth / permission gate
    // ------------------------------------------------------------------

    public function test_guest_get_redirects_to_login(): void
    {
        $response = $this->get('/massmail.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_below_sysop_user_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/massmail.php')->assertForbidden();
    }

    public function test_sysop_can_access_form(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/massmail.php');

        $response->assertOk();
        $this->assertStringContainsString('massmail.php', (string) $response->getContent());
        $this->assertStringContainsString('name="subject"', (string) $response->getContent());
        $this->assertStringContainsString('name="message"', (string) $response->getContent());
    }

    // ------------------------------------------------------------------
    // POST validation
    // ------------------------------------------------------------------

    public function test_post_with_invalid_operator_returns_error(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/massmail.php', [
            'or' => 'INVALID',
            'class' => '1',
            'subject' => 'Test',
            'message' => 'Hello',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Invalid symbol', (string) $response->getContent());
    }

    public function test_post_with_empty_message_returns_error(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/massmail.php', [
            'or' => '=',
            'class' => '1',
            'subject' => 'Test',
            'message' => '',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Empty message', (string) $response->getContent());
    }

    public function test_post_below_sysop_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->post('/massmail.php', [
            'or' => '=',
            'class' => '1',
            'subject' => 'Test',
            'message' => 'Hello',
        ])->assertForbidden();
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
