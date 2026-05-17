<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/docleanup.php` contract.
 *
 * Sysop-only manual trigger for the legacy `docleanup($forceall, 1)`
 * batch job. The job itself touches roughly 20 tables and is exercised
 * by `tests/Feature/Console/CronAutocleanTest`; here we only assert
 * the HTTP-side envelope (auth gates, query-string parsing, response
 * shape). We do NOT pin the body of the cleanup output — it shifts
 * with every code change in `include/cleanup.php` and is not part of
 * the page's user-visible contract.
 *
 * The legacy script rendered `die('forbidden')` for non-Sysop users
 * (HTTP 200 with a plain body); we tighten to a real 403 in line with
 * the rest of Phase 2 (see `MailtestControllerTest`).
 */
class DocleanupControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/docleanup.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/docleanup.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_administrator_user_is_forbidden(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $this->get('/docleanup.php')->assertForbidden();
    }

    public function test_sysop_get_runs_cleanup_and_renders_envelope(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $response = $this->get('/docleanup.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Cleanup</title>', $body);
        $this->assertStringContainsString('Running cleanup', $body);
        $this->assertStringContainsString('Time consumed:', $body);
        $this->assertStringContainsString('Done', $body);
    }

    public function test_without_forceall_shows_disabled_notice(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $response = $this->get('/docleanup.php');

        $response->assertOk();
        $this->assertStringContainsString(
            'Force-all mode disabled',
            (string) $response->getContent(),
        );
    }

    public function test_forceall_query_string_suppresses_disabled_notice(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $response = $this->get('/docleanup.php?forceall=1');

        $response->assertOk();
        $this->assertStringNotContainsString(
            'Force-all mode disabled',
            (string) $response->getContent(),
        );
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
