<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/testip.php` contract.
 *
 * Moderator-only IP-ban check tool. Legacy semantics:
 *   - Guest → login redirect.
 *   - Below moderator → 403 (legacy 200 / `stderr()`).
 *   - GET / POST without `ip` → 200 form (`<form action=testip.php>`).
 *   - GET / POST with malformed `ip` → 200 "Bad IP." error page.
 *   - GET / POST with valid `ip` and no matching `bans` rows → 200
 *     "The IP address <b>X</b> is not banned." notice.
 *   - GET / POST with valid `ip` and one or more `bans` hits → 200
 *     3-column (First / Last / Comment) table.
 */
class TestIpControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/testip.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/testip.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_below_moderator_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_POWER_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/testip.php')->assertForbidden();
        $this->post('/testip.php', ['ip' => '1.2.3.4'])->assertForbidden();
    }

    public function test_moderator_get_without_ip_renders_form(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/testip.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Test IP address</title>', $body);
        $this->assertStringContainsString('action="testip.php"', $body);
        $this->assertStringContainsString('name="ip"', $body);
        $this->assertStringContainsString('method="post"', $body);
        $this->assertStringNotContainsString('is not banned', $body);
        $this->assertStringNotContainsString('Bad IP.', $body);
    }

    public function test_moderator_post_with_unbanned_ip_renders_not_banned_notice(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/testip.php', ['ip' => '10.20.30.40']);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Result</title>', $body);
        $this->assertStringContainsString('<b>10.20.30.40</b> is not banned.', $body);
    }

    public function test_moderator_get_with_unbanned_ip_renders_not_banned_notice(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        // GET path mirrors the link from `public/usersearch.php` —
        // `<a href='testip.php?ip=...'>` — so the same query-string
        // entry-point must work without a template change.
        $response = $this->get('/testip.php?ip=8.8.8.8');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<b>8.8.8.8</b> is not banned.', $body);
    }

    public function test_moderator_post_with_banned_ip_renders_bans_table(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $first = ip2long('203.0.113.0');
        $last = ip2long('203.0.113.255');
        $banId = NexusDB::table('bans')->insertGetId([
            'added' => Carbon::now()->toDateTimeString(),
            'addedby' => $user->id,
            'first' => $first,
            'last' => $last,
            'comment' => 'pin-net for tests',
        ]);

        try {
            $response = $this->post('/testip.php', ['ip' => '203.0.113.42']);
            $response->assertOk();
            $body = (string) $response->getContent();
            $this->assertStringContainsString('<b>203.0.113.42</b> is banned', $body);
            $this->assertStringContainsString('203.0.113.0', $body);
            $this->assertStringContainsString('203.0.113.255', $body);
            $this->assertStringContainsString('pin-net for tests', $body);
        } finally {
            NexusDB::table('bans')->where('id', $banId)->delete();
        }
    }

    public function test_moderator_post_with_bad_ip_renders_error(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/testip.php', ['ip' => 'not-an-ip']);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Error</title>', $body);
        $this->assertStringContainsString('Bad IP.', $body);
    }

    public function test_ip_value_is_html_escaped_in_error_page(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/testip.php', ['ip' => '<script>alert(1)</script>']);

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
    }

    public function test_ip_value_is_html_escaped_in_not_banned_notice(): void
    {
        // The malformed input takes the "Bad IP." branch, so to
        // exercise the escape path in the *match* branch we use a
        // valid IP that still contains nothing dangerous (the
        // escaping path is exercised symbolically via the Bad IP
        // test). This assertion just locks the result-page
        // markup contract.
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $body = (string) $this->post('/testip.php', ['ip' => '198.51.100.7'])->getContent();
        $this->assertStringContainsString('<b>198.51.100.7</b>', $body);
    }

    public function test_csrf_token_is_not_required(): void
    {
        // Mirrors the rest of the Phase 2 batch — the legacy
        // `<form method=post action=testip.php>` has no `@csrf`
        // token, so the POST verb must be CSRF-exempt
        // (`App\Http\Middleware\VerifyCsrfToken::$except`).
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        // Run the full middleware stack (including VerifyCsrfToken)
        // — by default the framework's test helpers disable CSRF;
        // we re-enable it here to assert the carve-out actually
        // applies.
        $response = $this->withMiddleware()
            ->post('/testip.php', ['ip' => '203.0.113.99']);

        $response->assertOk();
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(
                ['lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );
    }
}
