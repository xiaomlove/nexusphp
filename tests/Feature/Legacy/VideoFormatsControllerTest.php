<?php

namespace Tests\Feature\Legacy;

use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for the migrated `/videoformats.php` endpoint
 * (`App\Http\Controllers\Legacy\VideoFormatsController`, replaces
 * `public/videoformats.php`). Mirrors `FormatsControllerTest`.
 */
class VideoFormatsControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/videoformats.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_authed_user_sees_the_static_glossary(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $body = (string) $this->get('/videoformats.php')->assertOk()->getContent();

        // Anchor strings from the static body — pinning the rip-tag
        // labels so a Phase 5 retranslation can't silently drop the
        // glossary content.
        $this->assertStringContainsString("Downloaded a movie and don't know what CAM/TS/TC/SCR means?", $body);
        $this->assertStringContainsString('TELESYNC (TS)', $body);
        $this->assertStringContainsString('TELECINE (TC)', $body);
        $this->assertStringContainsString('SCREENER (SCR)', $body);
        $this->assertStringContainsString('DVDRip', $body);
        $this->assertStringContainsString('NUKED', $body);
        $this->assertStringContainsString('NexusPHP :: Video Formats', $body);
    }
}
