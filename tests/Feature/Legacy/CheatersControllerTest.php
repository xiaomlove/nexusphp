<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class CheatersControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/cheaters.php';
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
        $response = $this->get('/cheaters.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_below_moderator_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_UPLOADER]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/cheaters.php')->assertForbidden();
    }

    public function test_moderator_sees_cheaters_page(): void
    {
        $mod = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($mod, 'nexus-web');

        $response = $this->get('/cheaters.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Cheaters</title>', $body);
        $this->assertStringContainsString('Cheaters', $body);
        $this->assertStringContainsString('Class:', $body);
        $this->assertStringContainsString('Ratio:', $body);
        $this->assertStringContainsString('Cheat Value', $body);
    }

    public function test_filter_params_are_preserved_in_form(): void
    {
        $mod = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($mod, 'nexus-web');

        $response = $this->get('/cheaters.php?c=5&r=3');

        $response->assertOk();
        $body = (string) $response->getContent();
        // The class option for value 5 should be selected
        $this->assertStringContainsString('value="5" selected', $body);
        // The ratio option for value 3 should be selected
        $this->assertStringContainsString('value="3" selected', $body);
    }

    public function test_administrator_can_access(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/cheaters.php');

        $response->assertOk();
    }
}
