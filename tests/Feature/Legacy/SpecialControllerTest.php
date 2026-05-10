<?php

namespace Tests\Feature\Legacy;

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

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/special.php');

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_authenticated_request_redirects_to_torrents_special(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/special.php');

        $response->assertRedirect('/torrents.php?special=1');
    }

    public function test_authenticated_request_preserves_extra_query_string(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/special.php?cat=1&search=foo');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/torrents.php?', $location);
        $this->assertStringContainsString('special=1', $location);
        $this->assertStringContainsString('cat=1', $location);
        $this->assertStringContainsString('search=foo', $location);
    }
}
