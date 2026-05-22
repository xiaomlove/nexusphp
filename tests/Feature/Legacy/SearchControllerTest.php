<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/search.php` contract.
 *
 * Authed torrent-search page reachable from the search box in
 * `include/functions.php:2270`. Renders the legacy
 * `torrenttable()` listing on a successful match, or a "Try
 * again" message when the query is empty / yields no rows.
 */
class SearchControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/search.php';
    }

    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(['lang' => self::ENGLISH_LANGUAGE_ID], $overrides),
        );
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/search.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_parked_user_is_forbidden(): void
    {
        $user = $this->createTestUser(['parked' => 'yes']);
        $this->actingAs($user, 'nexus-web');

        $this->get('/search.php')->assertForbidden();
    }

    public function test_empty_query_renders_search_results_envelope(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/search.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('id="search-results"', $body);
    }

    public function test_search_query_is_html_escaped(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/search.php?search='.urlencode('<script>alert(1)</script>'));

        $response->assertOk();
        $body = (string) $response->getContent();
        // Anti-XSS: the literal `<script>` payload must NOT appear
        // verbatim in the response. The HTML-escaped form is
        // acceptable.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
    }
}
