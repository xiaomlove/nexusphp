<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class StaffPanelControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/staffpanel.php';
    }

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
        $response = $this->get('/staffpanel.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_below_moderator_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/staffpanel.php')->assertForbidden();
    }

    public function test_moderator_sees_only_moderator_section(): void
    {
        $moderator = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($moderator, 'nexus-web');

        $response = $this->get('/staffpanel.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Administration</title>', $body);
        $this->assertStringContainsString('For Moderator Only', $body);
        $this->assertStringNotContainsString('For Administrator Only', $body);
        $this->assertStringNotContainsString('For SysOp Only', $body);
    }

    public function test_administrator_sees_admin_and_moderator_sections(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/staffpanel.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('For Administrator Only', $body);
        $this->assertStringContainsString('For Moderator Only', $body);
        $this->assertStringNotContainsString('For SysOp Only', $body);
    }

    public function test_sysop_sees_all_three_sections(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $response = $this->get('/staffpanel.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('For SysOp Only', $body);
        $this->assertStringContainsString('For Administrator Only', $body);
        $this->assertStringContainsString('For Moderator Only', $body);
    }

    public function test_staff_leader_sees_all_three_sections(): void
    {
        $staffLeader = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staffLeader, 'nexus-web');

        $response = $this->get('/staffpanel.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('For SysOp Only', $body);
        $this->assertStringContainsString('For Administrator Only', $body);
        $this->assertStringContainsString('For Moderator Only', $body);
    }

    public function test_modpanel_entries_render_as_links(): void
    {
        NexusDB::table('modpanel')->insert([
            'name' => 'Test Mod Tool',
            'url' => 'testmod.php',
            'info' => 'A mod tool for testing',
        ]);

        $moderator = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($moderator, 'nexus-web');

        $response = $this->get('/staffpanel.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<a href="testmod.php">Test Mod Tool</a>', $body);
        $this->assertStringContainsString('A mod tool for testing', $body);
    }

    public function test_panel_entries_are_html_escaped(): void
    {
        NexusDB::table('modpanel')->insert([
            'name' => '<script>alert(1)</script>',
            'url' => 'javascript:alert(2)',
            'info' => '"><img src=x onerror=alert(3)>',
        ]);

        $moderator = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($moderator, 'nexus-web');

        $response = $this->get('/staffpanel.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringNotContainsString('<img src=x onerror=alert(3)>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
        $this->assertStringContainsString('&lt;img', $body);
    }
}
