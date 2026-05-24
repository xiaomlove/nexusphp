<?php

namespace Tests\Feature\Legacy;

use App\Http\Controllers\Legacy\SeedBoxAjaxController;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the wire-level contract of the four endpoints lifted
 * out of `public/ajax.php` in this PR (Phase 2.5 — batch D of the
 * `public/ajax.php` cleanup). See
 * `App\Http\Controllers\Legacy\SeedBoxAjaxController` for the
 * controller-side rationale.
 *
 * The tests deliberately avoid exercising the seed-box approval
 * pipeline or Sanctum token-creation happy-path — those touch
 * `seed_box_records`, `users.seedbox_count`, GeoIP/ASN lookups,
 * the `personal_access_tokens` table, and various caches. Instead
 * the tests pin the surface that JS callers depend on:
 *
 *   - URL shape (`/seed-box/<verb>`, `/user/token/<verb>`) and
 *     route names
 *   - HTTP method (POST only)
 *   - Auth posture (all four routes redirect guests to `/login.php`)
 *   - JSON envelope shape (`{ret, msg, data}` at HTTP 200, including
 *     on error — same as the legacy `exit(json_encode(fail(...)))`)
 *   - CSRF exemption (legacy inline-script XHR has no `_token`)
 *   - Required-field validation message preservation for
 *     `addToken` / `removeToken` ("Name is required" / "id is
 *     required" — the legacy dispatcher's exact strings).
 */
class SeedBoxAjaxControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /**
     * Every endpoint in this batch sits behind `auth.nexus:nexus-web`
     * — the legacy dispatcher gated all four behind
     * `loggedinorreturn()` in `public/ajax.php`.
     *
     * @var list<string>
     */
    private const ENDPOINTS = [
        '/seed-box/add',
        '/seed-box/remove',
        '/user/token/add',
        '/user/token/remove',
    ];

    public function test_routes_are_registered_with_expected_names(): void
    {
        $this->assertTrue(Route::has('seed-box.add'));
        $this->assertTrue(Route::has('seed-box.remove'));
        $this->assertTrue(Route::has('user-token.add'));
        $this->assertTrue(Route::has('user-token.remove'));
    }

    public function test_routes_resolve_to_expected_controller_methods(): void
    {
        $expectations = [
            'seed-box.add' => [SeedBoxAjaxController::class, 'addSeedBoxRecord'],
            'seed-box.remove' => [SeedBoxAjaxController::class, 'removeSeedBoxRecord'],
            'user-token.add' => [SeedBoxAjaxController::class, 'addToken'],
            'user-token.remove' => [SeedBoxAjaxController::class, 'removeToken'],
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
            $response = $this->get($endpoint);
            $this->assertSame(
                405,
                $response->status(),
                "GET $endpoint should be 405; got {$response->status()}",
            );
        }
    }

    public function test_guest_post_to_endpoints_redirects_to_login(): void
    {
        foreach (self::ENDPOINTS as $endpoint) {
            $response = $this->post($endpoint);

            $response->assertRedirect();
            $this->assertStringContainsString(
                'login.php',
                (string) $response->headers->get('Location'),
                "Guest POST $endpoint should redirect to login.php",
            );
        }
    }

    public function test_csrf_token_not_required(): void
    {
        // Drop the `Accept: application/json` header so we exercise
        // the same code path the legacy XHR caller uses (a bare
        // `application/x-www-form-urlencoded` body, no `_token`). If
        // the CSRF middleware were still active for these routes
        // we'd get 419 instead of the auth.nexus 302 redirect.
        foreach (self::ENDPOINTS as $endpoint) {
            $response = $this->call(method: 'POST', uri: $endpoint, parameters: []);
            $this->assertNotSame(
                419,
                $response->status(),
                "POST $endpoint should not 419 (CSRF-exempt)",
            );
            $response->assertRedirect();
        }
    }

    public function test_authenticated_remove_seed_box_with_unknown_id_returns_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/seed-box/remove', [
            'params' => ['id' => 0],
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
        // `SeedBoxRepository::delete($id=0, $uid)` finds no row;
        // the legacy contract was to throw / fail loudly. Either
        // way the envelope shape is preserved.
    }

    public function test_authenticated_add_token_without_name_returns_legacy_error_message(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/user/token/add', [
            'params' => [],
        ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
        // The legacy dispatcher threw `\InvalidArgumentException(
        // "Name is required")`; the controller catches and wraps
        // it as a `{ret: -1, msg: "Name is required"}` envelope.
        // Pin the exact message so JS callers that rely on it for
        // form validation messages keep working.
        $this->assertSame(-1, $payload['ret']);
        $this->assertSame('Name is required', $payload['msg']);
    }

    public function test_authenticated_remove_token_without_id_returns_legacy_error_message(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/user/token/remove', [
            'params' => [],
        ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
        $this->assertSame(-1, $payload['ret']);
        $this->assertSame('id is required', $payload['msg']);
    }

    public function test_authenticated_remove_token_with_unknown_id_succeeds_silently(): void
    {
        // Pinning the legacy contract for "unknown id" — the
        // dispatcher calls `User::tokens()->where('id', $id)
        // ->delete()` which is a no-op when no row matches. The
        // envelope's `data` is `true` regardless. We assert the
        // shape only; the side-effect (zero rows deleted) is the
        // implicit happy path here.
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/user/token/remove', [
            'params' => ['id' => 0],
        ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
        $this->assertSame(0, $payload['ret']);
        $this->assertTrue($payload['data']);
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
