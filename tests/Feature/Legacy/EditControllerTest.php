<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for `App\Http\Controllers\Legacy\EditController`
 * (replaces `public/edit.php`).
 */
class EditControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_URI'] = '/edit.php';
    }

    public function test_guest_redirects_to_login(): void
    {
        $response = $this->get('/edit.php?id=1');

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_missing_id_returns_404(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/edit.php');

        $response->assertNotFound();
    }

    public function test_zero_id_returns_404(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/edit.php?id=0');

        $response->assertNotFound();
    }

    public function test_nonexistent_torrent_returns_404(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/edit.php?id=999999999');

        $response->assertNotFound();
    }
}
