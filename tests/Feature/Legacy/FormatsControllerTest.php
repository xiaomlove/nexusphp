<?php

namespace Tests\Feature\Legacy;

use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for the migrated `/formats.php` endpoint
 * (`App\Http\Controllers\Legacy\FormatsController`, replaces
 * `public/formats.php`). The contract is "render a static HTML
 * guide of common file extensions verbatim, behind the same
 * `loggedinorreturn()` gate the legacy script had". The body
 * comes from `resources/views/legacy/formats.blade.php`.
 */
class FormatsControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/formats.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_authed_user_sees_the_static_guide(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $body = (string) $this->get('/formats.php')->assertOk()->getContent();

        // Anchor strings from the static body — pinning a few of
        // the headings + a couple of the application names that the
        // legacy guide enumerates so a Phase 5 retranslation can't
        // silently drop the entire body.
        $this->assertStringContainsString('A Handy Guide to Using the Files', $body);
        $this->assertStringContainsString('Compression Files', $body);
        $this->assertStringContainsString('Multimedia Files', $body);
        $this->assertStringContainsString('CD Image Files', $body);
        $this->assertStringContainsString('WinRAR', $body);
        $this->assertStringContainsString('NexusPHP :: Downloaded Files', $body);
    }
}
