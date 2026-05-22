<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class TorrentInfoControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/torrent_info.php';
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
        $response = $this->get('/torrent_info.php?id=1');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        // Class 1 (User) does not have torrentstructure permission (default class 8)
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/torrent_info.php?id=1')->assertForbidden();
    }

    public function test_missing_id_returns_404(): void
    {
        // Use a high-class user that has the permission
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/torrent_info.php')->assertNotFound();
    }

    public function test_nonexistent_torrent_returns_404(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/torrent_info.php?id=999999')->assertNotFound();
    }
}
