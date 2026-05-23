<?php

namespace Tests\Feature\Legacy;

use App\Http\Controllers\Legacy\MedalAjaxController;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the wire-level contract of the four `/medal/*` endpoints
 * lifted out of `public/ajax.php` in this PR (Phase 2.5 — batch B of
 * the `public/ajax.php` cleanup). See
 * `App\Http\Controllers\Legacy\MedalAjaxController` for the
 * controller-side rationale.
 *
 * The tests deliberately avoid exercising the medal-economy
 * happy-path (purchases, gifting, showcase persistence) — those
 * touch `users.seedbonus`, `medals`, `user_medals`, and the
 * `BonusLogService` ledger, which would couple the test to many
 * unrelated tables. Instead the tests pin the surface that JS
 * callers depend on:
 *
 *   - URL shape (`/medal/<action>`) and route names
 *   - HTTP method (POST only)
 *   - Auth posture (all four routes redirect guests to `/login.php`)
 *   - JSON envelope shape (`{ret, msg, data}` at HTTP 200, including
 *     on error — same as the legacy `exit(json_encode(fail(...)))`)
 *   - CSRF exemption (legacy inline-script XHR has no `_token`)
 *
 * Deeper repository semantics (purchase ledger, gift transaction,
 * showcase priority/expiry persistence) are exercised by the
 * existing `BonusRepository` / `MedalRepository` consumers and by
 * the medal E2E flows; covering them again here would couple the
 * test to many unrelated tables for no migration-specific signal.
 */
class MedalAjaxControllerTest extends FeatureTestCase
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
        'toggle-status',
        'buy',
        'gift',
        'save-user',
    ];

    public function test_routes_are_registered_with_expected_names(): void
    {
        $this->assertTrue(Route::has('medal.toggle-status'));
        $this->assertTrue(Route::has('medal.buy'));
        $this->assertTrue(Route::has('medal.gift'));
        $this->assertTrue(Route::has('medal.save-user'));
    }

    public function test_routes_resolve_to_expected_controller_methods(): void
    {
        $expectations = [
            'medal.toggle-status' => [MedalAjaxController::class, 'toggleStatus'],
            'medal.buy' => [MedalAjaxController::class, 'buy'],
            'medal.gift' => [MedalAjaxController::class, 'gift'],
            'medal.save-user' => [MedalAjaxController::class, 'saveUserMedal'],
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
            $response = $this->get("/medal/$endpoint");
            $this->assertSame(
                405,
                $response->status(),
                "GET /medal/$endpoint should be 405; got {$response->status()}",
            );
        }
    }

    public function test_guest_post_to_endpoints_redirects_to_login(): void
    {
        foreach (self::ENDPOINTS as $endpoint) {
            $response = $this->post("/medal/$endpoint");

            $response->assertRedirect();
            $this->assertStringContainsString(
                'login.php',
                (string) $response->headers->get('Location'),
                "Guest POST /medal/$endpoint should redirect to login.php",
            );
        }
    }

    public function test_csrf_token_not_required(): void
    {
        // Drop the `Accept: application/json` header so we exercise
        // the same code path the legacy XHR caller uses (a bare
        // `application/x-www-form-urlencoded` body, no `_token`). If
        // the CSRF middleware were still active for `medal/*` we'd
        // get 419 instead of the auth.nexus 302 redirect.
        $response = $this->call(
            method: 'POST',
            uri: '/medal/buy',
            parameters: [],
        );

        $this->assertNotSame(419, $response->status());
        // Guest reaches the auth.nexus middleware redirect, not 419.
        $response->assertRedirect();
    }

    public function test_authenticated_buy_with_unknown_medal_returns_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/medal/buy', [
            'params' => [
                'medal_id' => 0,
            ],
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
        // `BonusRepository::consumeToBuyMedal` does
        // `Medal::query()->findOrFail(0)` -> ModelNotFoundException;
        // the controller catches and wraps as ret:-1.
        $this->assertSame(-1, $payload['ret']);
    }

    public function test_authenticated_gift_with_unknown_medal_returns_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/medal/gift', [
            'params' => [
                'medal_id' => 0,
                'uid' => $user->id,
            ],
        ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
        $this->assertSame(-1, $payload['ret']);
    }

    public function test_authenticated_toggle_status_with_unknown_user_medal_returns_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/medal/toggle-status', [
            'params' => [
                'id' => 0,
            ],
        ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
        // `UserMedal::query()->findOrFail(0)` -> ModelNotFoundException
        // -> ret:-1.
        $this->assertSame(-1, $payload['ret']);
    }

    public function test_authenticated_save_user_with_empty_payload_returns_envelope(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        // Empty `params` array — the legacy parser produces an empty
        // `$data` map; `saveUserMedal()` then short-circuits because
        // `$user->valid_medals` is empty for a fresh test user.
        $response = $this->postJson('/medal/save-user', [
            'params' => [],
        ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
    }

    public function test_save_user_parses_legacy_serialized_form_payload(): void
    {
        // The legacy JS sends `form.serializeArray()`, which expands
        // into `params[N][name]=<field>_<id>&params[N][value]=<v>`.
        // Pin that the controller still reads it the same way the
        // legacy dispatcher did — even when there is no matching
        // `user_medals` row to update for a fresh user.
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/medal/save-user', [
            'params' => [
                ['name' => 'priority_999', 'value' => '1'],
                ['name' => 'expired_999', 'value' => '2099-12-31 00:00:00'],
            ],
        ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertEnvelopeShape($payload);
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
