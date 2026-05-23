<?php

namespace Tests\Feature\Legacy;

use App\Http\Controllers\Legacy\PasskeyAjaxController;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the wire-level contract of the six `/passkey/*` endpoints
 * lifted out of `public/ajax.php` in this PR (Phase 2.5 — batch A of
 * the `public/ajax.php` cleanup). See
 * `App\Http\Controllers\Legacy\PasskeyAjaxController` for the
 * controller-side rationale.
 *
 * The tests deliberately avoid exercising the WebAuthn library's
 * cryptographic happy-path (that would require a deterministic
 * authenticator client mock — out of scope for a Phase 2 migration
 * test). Instead they pin the surface that JS callers depend on:
 *
 *   - URL shape (`/passkey/<action>`) and route names
 *   - HTTP method (POST only)
 *   - Auth posture (4 management routes redirect guests to
 *     `/login.php`; 2 login-flow routes accept guests)
 *   - JSON envelope shape (`{ret, msg, data}` at HTTP 200, including
 *     on error — same as the legacy `exit(json_encode(fail(...)))`)
 *   - CSRF exemption (legacy XHR has no `_token`)
 *
 * The deeper repository semantics (challenge round-trip, signature
 * verification, `logincookie()` cookie minting) are exercised by the
 * existing `UserPasskeyRepository` consumers and the E2E passkey
 * suite — covering them again here would couple the test to
 * lbuchs/webauthn implementation details.
 */
class PasskeyAjaxControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /**
     * The four endpoints behind `auth.nexus:nexus-web` — guests get
     * a 302 to `/login.php?returnto=...` (see
     * `App\Http\Middleware\NexusAuth::redirectTo`).
     *
     * @var list<string>
     */
    private const AUTHED_ENDPOINTS = [
        'create-args',
        'create',
        'delete',
        'list',
    ];

    /**
     * The two endpoints that are reachable without a session — the
     * passkey-based login flow has to start somewhere, and
     * `processPasskeyGet` is the call that mints the auth cookie.
     *
     * @var list<string>
     */
    private const LOGIN_FLOW_ENDPOINTS = [
        'get-args',
        'get',
    ];

    public function test_routes_are_registered_with_expected_names(): void
    {
        $this->assertTrue(Route::has('passkey.create-args'));
        $this->assertTrue(Route::has('passkey.create'));
        $this->assertTrue(Route::has('passkey.delete'));
        $this->assertTrue(Route::has('passkey.list'));
        $this->assertTrue(Route::has('passkey.get-args'));
        $this->assertTrue(Route::has('passkey.get'));
    }

    public function test_routes_resolve_to_expected_controller_methods(): void
    {
        $expectations = [
            'passkey.create-args' => [PasskeyAjaxController::class, 'getCreateArgs'],
            'passkey.create' => [PasskeyAjaxController::class, 'processCreate'],
            'passkey.delete' => [PasskeyAjaxController::class, 'deletePasskey'],
            'passkey.list' => [PasskeyAjaxController::class, 'getList'],
            'passkey.get-args' => [PasskeyAjaxController::class, 'getGetArgs'],
            'passkey.get' => [PasskeyAjaxController::class, 'processGet'],
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
        foreach ([...self::AUTHED_ENDPOINTS, ...self::LOGIN_FLOW_ENDPOINTS] as $endpoint) {
            $response = $this->get("/passkey/$endpoint");
            $this->assertSame(
                405,
                $response->status(),
                "GET /passkey/$endpoint should be 405; got {$response->status()}",
            );
        }
    }

    public function test_guest_post_to_authed_endpoints_redirects_to_login(): void
    {
        foreach (self::AUTHED_ENDPOINTS as $endpoint) {
            $response = $this->post("/passkey/$endpoint");

            $response->assertRedirect();
            $this->assertStringContainsString(
                'login.php',
                (string) $response->headers->get('Location'),
                "Guest POST /passkey/$endpoint should redirect to login.php",
            );
        }
    }

    public function test_guest_can_post_to_login_flow_endpoints(): void
    {
        foreach (self::LOGIN_FLOW_ENDPOINTS as $endpoint) {
            $response = $this->post("/passkey/$endpoint", [
                // Minimal payload — `processGet` reads `params[*]`
                // sub-keys; missing keys flow into the controller's
                // `try/catch` and come back as a `{ret: -1, ...}`
                // envelope, which is what we want to pin.
                'params' => [],
            ]);

            $this->assertNotSame(
                419,
                $response->status(),
                "POST /passkey/$endpoint should not 419 (CSRF-exempt); got {$response->status()}",
            );
            $response->assertOk();
            $response->assertHeader('Content-Type', 'application/json');
            $this->assertEnvelopeShape($response->json());
        }
    }

    public function test_authenticated_list_returns_empty_envelope_for_new_user(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/passkey/list');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
        $this->assertSame(0, $payload['ret']);
        $this->assertSame('OK', $payload['msg']);
        $this->assertSame([], $payload['data']);
    }

    public function test_authenticated_delete_with_unknown_credential_returns_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/passkey/delete', [
            'params' => [
                'credentialId' => 'no-such-credential-id',
            ],
        ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
        // The repository's Eloquent `->delete()` reports the affected
        // row count (0 for an unknown credential). The controller
        // wraps it as a success — the legacy contract did the same,
        // so we pin it here.
        $this->assertSame(0, $payload['ret']);
        $this->assertSame(0, $payload['data']);
    }

    public function test_authenticated_create_args_returns_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/passkey/create-args');

        // The repository hits `nexus()->getRequestHost()` and
        // `get_setting('basic.SITENAME')`, both of which depend on
        // bootstrap context; we don't assert `ret === 0` because in
        // some test environments the WebAuthn helper can throw on a
        // missing setting. What we DO assert is that the envelope
        // shape is preserved either way — that's the JS contract.
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertEnvelopeShape($response->json());
    }

    public function test_csrf_token_not_required_for_login_flow_endpoints(): void
    {
        // Drop the `Accept: application/json` header so we exercise
        // the same code path the legacy XHR caller uses (a bare
        // `URLSearchParams` body, no `_token`). If the CSRF
        // middleware were still active for `passkey/*` we'd get 419
        // instead of a JSON envelope.
        $response = $this->call(
            method: 'POST',
            uri: '/passkey/get-args',
            parameters: [],
        );

        $this->assertNotSame(419, $response->status());
        $this->assertSame(200, $response->status());
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
