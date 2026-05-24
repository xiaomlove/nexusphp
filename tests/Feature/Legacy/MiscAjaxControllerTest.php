<?php

namespace Tests\Feature\Legacy;

use App\Http\Controllers\Legacy\MiscAjaxController;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the wire-level contract of the two `/misc/*` endpoints
 * lifted out of `public/ajax.php` in this PR (Phase 2.5 — batch E of
 * the `public/ajax.php` cleanup). See
 * `App\Http\Controllers\Legacy\MiscAjaxController` for the
 * controller-side rationale.
 *
 * The tests pin the surface that JS callers depend on:
 *
 *   - URL shape (`/misc/<action>`) and route names
 *   - HTTP method (POST only)
 *   - Auth posture (all routes redirect guests to `/login.php`)
 *   - JSON envelope shape (`{ret, msg, data}` at HTTP 200, including
 *     on error — same as the legacy `exit(json_encode(fail(...)))`)
 *   - CSRF exemption (legacy XHR has no `_token`)
 *
 * The deeper repository semantics (PTGen URL parsing, attendance
 * retroactive business rules) are exercised by the existing
 * repository consumers and E2E flows.
 */
class MiscAjaxControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /**
     * @var list<string>
     */
    private const ENDPOINTS = [
        'pt-gen',
        'attendance-retroactive',
    ];

    public function test_routes_are_registered_with_expected_names(): void
    {
        $this->assertTrue(Route::has('misc.pt-gen'));
        $this->assertTrue(Route::has('misc.attendance-retroactive'));
    }

    public function test_routes_resolve_to_expected_controller_methods(): void
    {
        $expectations = [
            'misc.pt-gen' => [MiscAjaxController::class, 'getPtGen'],
            'misc.attendance-retroactive' => [MiscAjaxController::class, 'attendanceRetroactive'],
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
            $response = $this->get("/misc/$endpoint");
            $this->assertSame(
                405,
                $response->status(),
                "GET /misc/$endpoint should be 405; got {$response->status()}",
            );
        }
    }

    public function test_guest_post_redirects_to_login(): void
    {
        foreach (self::ENDPOINTS as $endpoint) {
            $response = $this->post("/misc/$endpoint");

            $response->assertRedirect();
            $this->assertStringContainsString(
                'login.php',
                (string) $response->headers->get('Location'),
                "Guest POST /misc/$endpoint should redirect to login.php",
            );
        }
    }

    public function test_csrf_token_not_required(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->call(
            method: 'POST',
            uri: '/misc/pt-gen',
            parameters: ['params' => ['url' => 'https://example.com']],
        );

        $this->assertNotSame(
            419,
            $response->status(),
            'POST /misc/pt-gen should not 419 (CSRF-exempt)',
        );
    }

    public function test_authenticated_pt_gen_with_empty_url_returns_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/misc/pt-gen', [
            'params' => ['url' => ''],
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertEnvelopeShape($response->json());
    }

    public function test_authenticated_attendance_retroactive_returns_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/misc/attendance-retroactive', [
            'params' => ['date' => '2025-01-01'],
        ]);

        // The repository may throw (invalid date, no retroactive
        // allowance, etc.) — the important thing is the envelope
        // shape is preserved either way.
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
