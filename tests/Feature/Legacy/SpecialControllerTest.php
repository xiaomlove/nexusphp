<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/special.php` redirect contract.
 *
 * The legacy script was a one-line `require "torrents.php"`; the
 * migrated controller redirects to `/torrents.php?special=1` so the
 * URL is still served by the existing torrent-list page.
 */
class SpecialControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        // `LogUserIp` middleware reads `$_SERVER['REQUEST_URI']` directly
        // (not from the Laravel Request object). The test HTTP client does
        // not populate it, so we seed it manually — same pattern as
        // ThanksControllerTest.
        $_SERVER['REQUEST_URI'] = '/special.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/special.php');

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_authenticated_request_redirects_to_torrents_special(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/special.php');

        $response->assertRedirect('/torrents.php?special=1');
    }

    public function test_authenticated_request_preserves_extra_query_string(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/special.php?cat=1&search=foo');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/torrents.php?', $location);
        $this->assertStringContainsString('special=1', $location);
        $this->assertStringContainsString('cat=1', $location);
        $this->assertStringContainsString('search=foo', $location);
    }

    /**
     * The default `createLegacyUser` does not set `lang`, but the
     * `Locale` middleware that runs after `auth.nexus` reads
     * `$user->language?->site_lang_folder`; without an English row,
     * `Carbon::setLocale(null)` crashes the request. Pin a known
     * language row id so the middleware stack stays happy.
     */
    private function createTestUser(): User
    {
        return $this->createLegacyUser(
            overrides: ['lang' => self::ENGLISH_LANGUAGE_ID],
        );
    }
}
