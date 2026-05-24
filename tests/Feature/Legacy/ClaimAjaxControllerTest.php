<?php

namespace Tests\Feature\Legacy;

use App\Http\Controllers\Legacy\ClaimAjaxController;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the wire-level contract of the five endpoints lifted
 * out of `public/ajax.php` in this PR (Phase 2.5 — batch C of the
 * `public/ajax.php` cleanup). See
 * `App\Http\Controllers\Legacy\ClaimAjaxController` for the
 * controller-side rationale.
 *
 * The tests deliberately avoid exercising the bonus-economy
 * happy-path (claim creation, H&R cancellation, exam assignment,
 * benefit consumption) — those touch `users.seedbonus`, `claims`,
 * `hit_and_runs`, `exams`, `user_metas`, and the bonus-log ledger,
 * which would couple the test to many unrelated tables. Instead
 * the tests pin the surface that JS callers depend on:
 *
 *   - URL shape (`/claim/<verb>` etc.) and route names
 *   - HTTP method (POST only)
 *   - Auth posture (all five routes redirect guests to `/login.php`)
 *   - JSON envelope shape (`{ret, msg, data}` at HTTP 200, including
 *     on error — same as the legacy `exit(json_encode(fail(...)))`)
 *   - CSRF exemption (legacy inline-script XHR has no `_token`)
 */
class ClaimAjaxControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /**
     * Every endpoint in this batch sits behind `auth.nexus:nexus-web`
     * — the legacy dispatcher gated all five behind
     * `loggedinorreturn()` in `public/ajax.php`.
     *
     * @var list<string>
     */
    private const ENDPOINTS = [
        '/claim/add',
        '/claim/remove',
        '/hit-and-run/remove',
        '/exam/claim-task',
        '/benefit/consume',
    ];

    public function test_routes_are_registered_with_expected_names(): void
    {
        $this->assertTrue(Route::has('claim.add'));
        $this->assertTrue(Route::has('claim.remove'));
        $this->assertTrue(Route::has('hit-and-run.remove'));
        $this->assertTrue(Route::has('exam.claim-task'));
        $this->assertTrue(Route::has('benefit.consume'));
    }

    public function test_routes_resolve_to_expected_controller_methods(): void
    {
        $expectations = [
            'claim.add' => [ClaimAjaxController::class, 'addClaim'],
            'claim.remove' => [ClaimAjaxController::class, 'removeClaim'],
            'hit-and-run.remove' => [ClaimAjaxController::class, 'removeHitAndRun'],
            'exam.claim-task' => [ClaimAjaxController::class, 'claimTask'],
            'benefit.consume' => [ClaimAjaxController::class, 'consumeBenefit'],
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
            // Guest hits the auth.nexus middleware redirect.
            $response->assertRedirect();
        }
    }

    public function test_authenticated_add_claim_with_unknown_torrent_returns_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/claim/add', [
            'params' => [
                'torrent_id' => 0,
            ],
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
        // `ClaimRepository::store` validates the torrent exists and
        // throws on torrent_id=0 — the controller wraps as ret:-1.
        $this->assertSame(-1, $payload['ret']);
    }

    public function test_authenticated_remove_claim_with_unknown_id_returns_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/claim/remove', [
            'params' => [
                'id' => 0,
            ],
        ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
        $this->assertSame(-1, $payload['ret']);
    }

    public function test_authenticated_remove_hit_and_run_with_unknown_id_returns_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/hit-and-run/remove', [
            'params' => [
                'id' => 0,
            ],
        ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
        $this->assertSame(-1, $payload['ret']);
    }

    public function test_authenticated_claim_task_with_unknown_exam_returns_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/exam/claim-task', [
            'params' => [
                'exam_id' => 0,
            ],
        ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
        $this->assertSame(-1, $payload['ret']);
    }

    public function test_authenticated_consume_benefit_with_unknown_meta_key_returns_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/benefit/consume', [
            'params' => [
                'meta_key' => 'no-such-benefit-key',
            ],
        ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
        $this->assertSame(-1, $payload['ret']);
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
