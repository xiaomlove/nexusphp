<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class StatsControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/stats.php';
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
        $response = $this->get('/stats.php');

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

        $this->get('/stats.php')->assertForbidden();
    }

    public function test_moderator_sees_stats_page(): void
    {
        $mod = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($mod, 'nexus-web');

        $response = $this->get('/stats.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Stats</title>', $body);
        $this->assertStringContainsString('Uploader Activity', $body);
        $this->assertStringContainsString('Category Activity', $body);
    }

    public function test_sorting_links_contain_query_params(): void
    {
        $mod = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($mod, 'nexus-web');

        $response = $this->get('/stats.php?uporder=lastul&catorder=torrents');

        $response->assertOk();
        $body = (string) $response->getContent();
        // Sorting links preserve the current state of the other table
        $this->assertStringContainsString('catorder=torrents', $body);
        $this->assertStringContainsString('uporder=lastul', $body);
    }

    public function test_administrator_can_access(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/stats.php');

        $response->assertOk();
    }
}
