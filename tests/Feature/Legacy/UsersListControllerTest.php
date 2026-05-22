<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class UsersListControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/users.php';
    }

    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(
                ['lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/users.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_PEASANT]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/users.php')->assertForbidden();
    }

    public function test_user_with_permission_sees_listing(): void
    {
        // Sysop always has all permissions
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/users.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Users</title>', $body);
        $this->assertStringContainsString('Username', $body);
        $this->assertStringContainsString('Registered', $body);
    }

    public function test_letter_filter_highlights_selected(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/users.php?letter=a');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<font class="gray"><b>A</b></font>', $body);
    }

    public function test_search_param_is_reflected_in_form(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/users.php?search=testuser');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('value="testuser"', $body);
    }
}
