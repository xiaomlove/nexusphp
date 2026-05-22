<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/downloadnotice.php` contract.
 *
 * Pre-download interstitial that renders one of three variants
 * (`firsttime`, `client`, `ratio`) and POSTs back to itself with
 * a checkbox to suppress the notice on subsequent visits, then
 * 302s to `/download.php?id=N&letdown=1` once the user has
 * acknowledged the notice.
 */
class DownloadNoticeControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/downloadnotice.php';
    }

    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(['lang' => self::ENGLISH_LANGUAGE_ID], $overrides),
        );
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/downloadnotice.php?torrentid=1&type=firsttime');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_authed_get_renders_firsttime_panel_with_form(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/downloadnotice.php?torrentid=42&type=firsttime');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('name="id" value="42"', $body);
        $this->assertStringContainsString('name="type" value="firsttime"', $body);
        $this->assertStringContainsString('method="post"', $body);
    }

    public function test_post_with_invalid_type_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/downloadnotice.php', [
            'id' => 42,
            'type' => 'bogus',
        ]);
        $response->assertStatus(422);
    }

    public function test_post_with_zero_id_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/downloadnotice.php', [
            'id' => 0,
            'type' => 'firsttime',
        ]);
        $response->assertStatus(422);
    }

    public function test_post_firsttime_with_hidenotice_redirects_and_unflags_user(): void
    {
        $user = $this->createTestUser(['showdlnotice' => 1]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/downloadnotice.php', [
            'id' => 42,
            'type' => 'firsttime',
            'hidenotice' => 1,
        ]);

        $response->assertRedirect('/download.php?id=42&letdown=1');
        $this->assertSame(0, (int) $user->fresh()->showdlnotice);
    }
}
