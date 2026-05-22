<?php

namespace Tests\Feature\Legacy;

use App\Models\Setting;
use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/download.php` contract.
 *
 * The torrent download endpoint. Three auth modes; this test
 * focuses on the validation gates that don't require a real
 * `.torrent` file on disk (the happy path is exercised by the
 * E2E smoke spec, not here, because mocking the bencode + the
 * tracker URL builder + the IP-log cache adds noise without
 * adding regression-pin value the smoke spec doesn't already).
 */
class DownloadControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/download.php';
    }

    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(['lang' => self::ENGLISH_LANGUAGE_ID], $overrides),
        );
    }

    public function test_guest_session_mode_redirects_to_login(): void
    {
        $response = $this->get('/download.php?id=1');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_missing_id_returns_404(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/download.php')->assertNotFound();
    }

    public function test_invalid_downhash_format_returns_400(): void
    {
        $response = $this->get('/download.php?downhash=garbage');
        $response->assertStatus(400);
    }

    public function test_invalid_passkey_returns_400(): void
    {
        // Ensure passkey-mode is enabled — without it, the passkey
        // branch is skipped and we fall into session-auth.
        Setting::query()->updateOrCreate(
            ['name' => 'torrent', 'arg' => 'download_support_passkey'],
            ['value' => json_encode('yes')],
        );

        $response = $this->get('/download.php?passkey=invalid_passkey_value&id=1');
        $response->assertStatus(400);
    }

    public function test_parked_session_user_is_forbidden(): void
    {
        $user = $this->createTestUser(['parked' => 'yes']);
        $this->actingAs($user, 'nexus-web');

        $this->get('/download.php?id=1')->assertForbidden();
    }
}
