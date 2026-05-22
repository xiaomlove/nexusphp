<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class DeleteTorrentControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/delete.php';
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
        $response = $this->post('/delete.php', ['id' => 1, 'reasontype' => 1]);

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->post('/delete.php', ['id' => 1, 'reasontype' => 1])->assertForbidden();
    }

    public function test_missing_id_renders_error(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/delete.php', ['reasontype' => 1]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Missing form data', $body);
    }

    public function test_nonexistent_torrent_returns_404(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $this->post('/delete.php', ['id' => 999999, 'reasontype' => 1])->assertNotFound();
    }
}
