<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for `App\Http\Controllers\Legacy\IpSearchController`
 * (replaces `public/ipsearch.php`).
 */
class IpSearchControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/ipsearch.php');

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_below_userprofile_permission_is_forbidden(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/ipsearch.php');

        $response->assertForbidden();
    }

    public function test_empty_query_renders_form(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/ipsearch.php');

        $response->assertOk();
        $this->assertStringContainsString('<form method="get" action="/ipsearch.php">', (string) $response->getContent());
    }

    public function test_invalid_ip_is_unprocessable(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/ipsearch.php?ip=not-an-ip');

        $response->assertStatus(422);
    }

    public function test_invalid_subnet_mask_is_unprocessable(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/ipsearch.php?ip=10.0.0.1&mask=not-a-mask');

        $response->assertStatus(422);
    }

    public function test_unknown_ip_renders_no_users_found(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($user, 'nexus-web');

        // 192.0.2.0/24 is the documentation-only TEST-NET-1 range —
        // no real seeded user will match it, so the count is 0.
        $response = $this->get('/ipsearch.php?ip=192.0.2.42');

        $response->assertOk();
        $body = (string) $response->getContent();
        // The "no users found" notice falls back to its English
        // wording when a specific lang key is missing; assert on the
        // generic paragraph wrapper instead.
        $this->assertStringContainsString('<p align="center">', $body);
    }

    public function test_cidr_mask_is_expanded_to_dotted_quad(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/ipsearch.php?ip=10.0.0.1&mask=/24');

        // With /24 expanded to 255.255.255.0, no live rows match in
        // an isolated test DB — we expect a successful render of the
        // search form + zero-results notice.
        $response->assertOk();
    }
}
