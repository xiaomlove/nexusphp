<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/task.php` contract.
 *
 * Authed paginated listing of `exams` rows with `type=task`.
 * Guests redirected to login; authed users see the listing
 * with the per-row "Claim" buttons gated on `data-id`.
 */
class TaskControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/task.php';
    }

    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(['lang' => self::ENGLISH_LANGUAGE_ID], $overrides),
        );
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/task.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_authed_request_renders_task_listing(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/task.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        // Listing contract pins the table id + claim button class
        // so the inline JS in the page can still find the buttons.
        $this->assertStringContainsString('id="task-table"', $body);
        $this->assertStringContainsString('action: \'claimTask\'', $body);
    }
}
