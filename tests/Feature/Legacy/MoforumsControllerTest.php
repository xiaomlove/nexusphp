<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for `App\Http\Controllers\Legacy\MoforumsController`
 * (replaces `public/moforums.php`).
 */
class MoforumsControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        // Prevent LogUserIp middleware from crashing on missing superglobal.
        $_SERVER['REQUEST_URI'] = '/moforums.php';
    }

    // ─── Auth / permission gates ─────────────────────────────────────────

    public function test_guest_redirects_to_login(): void
    {
        $response = $this->get('/moforums.php');

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_user_without_forummanage_is_forbidden(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/moforums.php');

        $response->assertForbidden();
    }

    // ─── List view ───────────────────────────────────────────────────────

    public function test_admin_sees_overforum_list(): void
    {
        $admin = $this->createLegacyUser(overrides: ['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/moforums.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        // The add form must be present.
        $this->assertStringContainsString('<form method="post" action="/moforums.php">', $body);
        $this->assertStringContainsString('name="action" value="addforum"', $body);
    }

    public function test_editforum_action_without_id_renders_not_found(): void
    {
        $admin = $this->createLegacyUser(overrides: ['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/moforums.php?action=editforum&id=0');

        $response->assertOk();
        // No valid row → "no records" notice.
        $body = (string) $response->getContent();
        $this->assertStringContainsStringIgnoringCase('no records', $body);
    }

    // ─── POST add ────────────────────────────────────────────────────────

    public function test_add_forum_redirects_to_list(): void
    {
        $admin = $this->createLegacyUser(overrides: ['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->post('/moforums.php', [
            'action' => 'addforum',
            'name' => 'Test Category '.uniqid(),
            'desc' => 'Integration test category',
            'viewclass' => 0,
            'sort' => 0,
        ]);

        $response->assertRedirect('/moforums.php?action=forum');
    }

    public function test_empty_add_redirects_without_inserting(): void
    {
        $admin = $this->createLegacyUser(overrides: ['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->post('/moforums.php', [
            'action' => 'addforum',
            'name' => '',
            'desc' => '',
        ]);

        $response->assertRedirect('/moforums.php?action=forum');
    }
}
