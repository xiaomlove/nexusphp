<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for `App\Http\Controllers\Legacy\TakeUploadController`
 * (replaces `public/takeupload.php`).
 *
 * Heavy validation paths (.torrent file parsing, info-hash dedupe,
 * cover extraction, KPS karma) are exercised in production by
 * legitimate uploads — covering them in Feature tests requires
 * fixture .torrent files and a writeable torrent directory which
 * is out of scope for this batch. The tests below pin the auth
 * gates and the bark-throw → genbark-render contract.
 */
class TakeUploadControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_URI'] = '/takeupload.php';
    }

    public function test_guest_redirects_to_login(): void
    {
        $response = $this->post('/takeupload.php', []);

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_user_with_uploadpos_no_is_forbidden(): void
    {
        $user = $this->createLegacyUser(overrides: [
            'class' => User::CLASS_USER,
            'uploadpos' => 'no',
        ]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takeupload.php', []);

        $response->assertForbidden();
    }

    public function test_missing_form_data_renders_bark(): void
    {
        $user = $this->createLegacyUser(overrides: [
            'class' => User::CLASS_USER,
            'uploadpos' => 'yes',
        ]);
        $this->actingAs($user, 'nexus-web');

        // No `name` / `descr` / `type` / `file` → bark throws → 200 envelope.
        $response = $this->post('/takeupload.php', []);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Missing form data', $body);
    }
}
