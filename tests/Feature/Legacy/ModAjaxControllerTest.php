<?php

namespace Tests\Feature\Legacy;

use App\Http\Controllers\Legacy\ModAjaxController;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the wire-level contract of the five `/mod/*` endpoints
 * lifted out of `public/ajax.php` in this PR (Phase 2.5 — batch E of
 * the `public/ajax.php` cleanup). See
 * `App\Http\Controllers\Legacy\ModAjaxController` for the
 * controller-side rationale.
 *
 * The tests pin the surface that JS callers depend on:
 *
 *   - URL shape (`/mod/<action>`) and route names
 *   - HTTP method (POST only)
 *   - Auth posture (all routes redirect guests to `/login.php`)
 *   - JSON envelope shape (`{ret, msg, data}` at HTTP 200, including
 *     on error — same as the legacy `exit(json_encode(fail(...)))`)
 *   - CSRF exemption (legacy XHR has no `_token`)
 *
 * The deeper repository semantics (approval state machine, leech-warn
 * removal, permission checks) are exercised by the existing repository
 * consumers and the relevant E2E flows — covering them again here
 * would couple the test to implementation details.
 */
class ModAjaxControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /**
     * @var list<string>
     */
    private const ENDPOINTS = [
        'remove-leech-warn',
        'get-offer',
        'approval-modal',
        'approval',
        'clear-shoutbox',
    ];

    public function test_routes_are_registered_with_expected_names(): void
    {
        $this->assertTrue(Route::has('mod.remove-leech-warn'));
        $this->assertTrue(Route::has('mod.get-offer'));
        $this->assertTrue(Route::has('mod.approval-modal'));
        $this->assertTrue(Route::has('mod.approval'));
        $this->assertTrue(Route::has('mod.clear-shoutbox'));
    }

    public function test_routes_resolve_to_expected_controller_methods(): void
    {
        $expectations = [
            'mod.remove-leech-warn' => [ModAjaxController::class, 'removeLeechWarn'],
            'mod.get-offer' => [ModAjaxController::class, 'getOffer'],
            'mod.approval-modal' => [ModAjaxController::class, 'approvalModal'],
            'mod.approval' => [ModAjaxController::class, 'approval'],
            'mod.clear-shoutbox' => [ModAjaxController::class, 'clearShoutBox'],
        ];

        foreach ($expectations as $name => [$class, $method]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route $name is missing");
            $this->assertSame(['POST'], array_values(array_diff($route->methods(), ['HEAD'])));
            $this->assertSame("$class@$method", $route->getActionName());
        }
    }

    public function test_get_request_is_method_not_allowed(): void
    {
        foreach (self::ENDPOINTS as $endpoint) {
            $response = $this->get("/mod/$endpoint");
            $this->assertSame(
                405,
                $response->status(),
                "GET /mod/$endpoint should be 405; got {$response->status()}",
            );
        }
    }

    public function test_guest_post_redirects_to_login(): void
    {
        foreach (self::ENDPOINTS as $endpoint) {
            $response = $this->post("/mod/$endpoint");

            $response->assertRedirect();
            $this->assertStringContainsString(
                'login.php',
                (string) $response->headers->get('Location'),
                "Guest POST /mod/$endpoint should redirect to login.php",
            );
        }
    }

    public function test_csrf_token_not_required(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        // Use a plain POST (no Accept: application/json header) to
        // exercise the same code path as the legacy XHR caller.
        $response = $this->call(
            method: 'POST',
            uri: '/mod/remove-leech-warn',
            parameters: ['params' => ['uid' => '99999']],
        );

        $this->assertNotSame(
            419,
            $response->status(),
            'POST /mod/remove-leech-warn should not 419 (CSRF-exempt)',
        );
    }

    public function test_authenticated_remove_leech_warn_returns_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/mod/remove-leech-warn', [
            'params' => ['uid' => (string) $user->id],
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertEnvelopeShape($response->json());
    }

    public function test_authenticated_get_offer_with_missing_id_returns_error_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/mod/get-offer', [
            'params' => [],
        ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
        $this->assertSame(-1, $payload['ret']);
    }

    public function test_authenticated_approval_with_missing_fields_returns_error_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/mod/approval', [
            'params' => ['torrent_id' => '1'],
            // Missing `approval_status`
        ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
        $this->assertSame(-1, $payload['ret']);
        $this->assertStringContainsString('Require approval_status', $payload['msg']);
    }

    public function test_authenticated_clear_shoutbox_returns_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        // The `clearShoutBox` action calls `user_can('sbmanage', true)`
        // which may throw for a non-privileged user. Either way, the
        // envelope shape is preserved.
        $response = $this->postJson('/mod/clear-shoutbox');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertEnvelopeShape($response->json());
    }

    /**
     * @param  mixed  $payload
     */
    private function assertEnvelopeShape($payload): void
    {
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('ret', $payload);
        $this->assertArrayHasKey('msg', $payload);
        $this->assertArrayHasKey('data', $payload);
        $this->assertIsInt($payload['ret']);
        $this->assertIsString($payload['msg']);
    }
}
