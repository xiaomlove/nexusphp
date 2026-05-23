<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for `App\Http\Controllers\Legacy\TakeEditController`
 * (replaces `public/takeedit.php`).
 *
 * The full update flow (UPDATE torrents + extras, search reindex,
 * StaffMessage on banned-edit, TorrentOperationLog) is exercised
 * end-to-end by the legitimate edit form in production. The tests
 * below pin the auth gate and the bark-throw → genbark-render
 * contract for the validation paths.
 */
class TakeEditControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_URI'] = '/takeedit.php';
    }

    public function test_guest_redirects_to_login(): void
    {
        $response = $this->post('/takeedit.php', []);

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_missing_form_data_renders_bark(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takeedit.php', []);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Missing form data', $body);
    }

    public function test_zero_id_returns_404(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takeedit.php', [
            'id' => 0,
            'name' => 'x',
            'descr' => 'x',
            'type' => 1,
        ]);

        $response->assertNotFound();
    }

    public function test_nonexistent_torrent_returns_404(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takeedit.php', [
            'id' => 999999999,
            'name' => 'x',
            'descr' => 'x',
            'type' => 1,
        ]);

        $response->assertNotFound();
    }
}
