<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for `App\Http\Controllers\Legacy\MysqlStatsController`
 * (replaces `public/mysql_stats.php`).
 */
class MysqlStatsControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_URI'] = '/mysql_stats.php';
    }

    public function test_guest_redirects_to_login(): void
    {
        $response = $this->get('/mysql_stats.php');

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_non_sysop_is_forbidden(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/mysql_stats.php');

        $response->assertForbidden();
    }

    public function test_administrator_is_forbidden(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/mysql_stats.php');

        $response->assertForbidden();
    }

    public function test_sysop_sees_mysql_status_page(): void
    {
        $sysop = $this->createLegacyUser(overrides: ['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $response = $this->get('/mysql_stats.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('MySQL Server Status', $body);
        $this->assertStringContainsString('Server traffic', $body);
        $this->assertStringContainsString('Query Statistics', $body);
    }
}
