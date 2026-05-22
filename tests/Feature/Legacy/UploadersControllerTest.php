<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class UploadersControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/uploaders.php';
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
        $response = $this->get('/uploaders.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_below_uploader_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_ELITE_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/uploaders.php')->assertForbidden();
    }

    public function test_uploader_sees_page(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_UPLOADER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/uploaders.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Uploaders</title>', $body);
        $this->assertStringContainsString('Uploaders -', $body);
        $this->assertStringContainsString('Select month', $body);
    }

    public function test_year_month_params_are_reflected(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_UPLOADER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/uploaders.php?year=2023&month=6');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('2023-06', $body);
    }

    public function test_administrator_can_access(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/uploaders.php');

        $response->assertOk();
    }
}
