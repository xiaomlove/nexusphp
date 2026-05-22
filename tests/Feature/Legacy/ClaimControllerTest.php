<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for `App\Http\Controllers\Legacy\ClaimController`
 * (replaces `public/claim.php`). Coverage focuses on the
 * gate-and-validation contract: status codes, the
 * `?torrent_id` / `?uid` requirement, and the action-button
 * visibility rule (only when the listing is scoped to the
 * viewer's own user id).
 *
 * The full happy-path render with seeded `claims` rows would
 * require a Torrent + Snatched + User chain that the existing
 * factory suite doesn't cover. The empty-list render is enough
 * to pin the route + headers + filter-form contract.
 */
class ClaimControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(
                ['lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/claim.php?uid=1');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_request_without_torrent_id_or_uid_is_unprocessable(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/claim.php');

        $response->assertStatus(422);
    }

    public function test_invalid_uid_value_is_unprocessable(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        // Non-positive integer — legacy `int_check($uid, true)`
        // bailed; the controller maps this to 422.
        $response = $this->get('/claim.php?uid=0');

        $response->assertStatus(422);
    }

    public function test_unknown_uid_returns_404(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/claim.php?uid=999999999');

        $response->assertNotFound();
    }

    public function test_unknown_torrent_id_returns_404(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/claim.php?torrent_id=999999999');

        $response->assertNotFound();
    }

    public function test_listing_for_another_user_omits_action_column(): void
    {
        $viewer = $this->createTestUser();
        $other = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $response = $this->get(sprintf('/claim.php?uid=%d', (int) $other->id));

        $response->assertOk();
        $body = (string) $response->getContent();
        // The viewer is looking at *another* user's claims, so the
        // settle/cancel column should not appear. We assert on the
        // translation key string the controller emits in the action
        // header — it must NOT be there.
        $this->assertStringNotContainsString('th_action', $body);
        $this->assertStringNotContainsString(htmlspecialchars((string) nexus_trans('claim.th_action')), $body);
    }

    public function test_self_listing_includes_action_column_header(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $response = $this->get(sprintf('/claim.php?uid=%d', (int) $viewer->id));

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString(
            htmlspecialchars((string) nexus_trans('claim.th_action')),
            $body,
            'Self-scoped listing must render the action column header.',
        );
    }

    public function test_username_is_html_escaped_in_header_link(): void
    {
        $other = $this->createTestUser([
            'username' => 'tu_'.bin2hex(random_bytes(3)).'<script>alert(1)</script>',
        ]);
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $response = $this->get(sprintf('/claim.php?uid=%d', (int) $other->id));

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function test_invalid_sort_falls_back_to_default(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $response = $this->get(sprintf('/claim.php?uid=%d&sort=evil_column&order=asc', (int) $viewer->id));

        // The controller whitelists sort/order values. A bad one
        // must not 500; it must render the listing using the
        // default `created_at` column instead. We probe by asserting
        // the default option in the rendered <select> is selected.
        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<option value="created_at" selected>', $body);
    }
}
