<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/attachment.php` contract.
 *
 * Iframe-style file-upload widget — see
 * `App\Http\Controllers\Legacy\AttachmentController` for the wire
 * shape. The compose helper opens this URL in an iframe; the
 * iframe writes back to the parent window via
 * `parent.tag_extimage('[attach]<dlkey>[/attach]')` (or
 * `parent.<callback_func>(<dlkey>, <url>)` for custom-field
 * preview helpers).
 *
 * The full upload pipeline (GD watermark / thumbnail / Storage
 * driver) is exercised in production e2e flows — these tests
 * pin the auth posture, the iframe envelope contract, and the
 * form-rendering branch so the iframe markup keeps working
 * across the legacy → Laravel migration.
 */
class AttachmentControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/attachment.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/attachment.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_authed_request_renders_iframe_envelope(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/attachment.php');

        $response->assertOk();
        $body = (string) $response->getContent();

        // Iframe-style envelope: the body is `<body class="inframe">`
        // (so the parent's `theme.css` collapses the page chrome).
        $this->assertStringContainsString('class="inframe"', $body);

        // The form must round-trip back to /attachment.php and
        // accept multipart bodies. Either an upload form OR an
        // empty body when the feature is globally disabled — both
        // are valid contracts.
        if (str_contains($body, '<form')) {
            $this->assertStringContainsString('enctype="multipart/form-data"', $body);
            $this->assertStringContainsString('action="/attachment.php', $body);
            $this->assertStringContainsString('name="file"', $body);
        }
    }

    public function test_callback_func_round_trips_through_form_action(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/attachment.php?callback_func=preview_custom_field_image_42');

        $response->assertOk();
        $body = (string) $response->getContent();

        // `?callback_func=` round-trips so the JS handler picks
        // the right parent-window callback after upload.
        if (str_contains($body, '<form')) {
            $this->assertStringContainsString(
                'callback_func=preview_custom_field_image_42',
                $body,
            );
        }
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge([
                'lang' => self::ENGLISH_LANGUAGE_ID,
                'class' => User::CLASS_USER,
            ], $overrides),
        );
    }
}
