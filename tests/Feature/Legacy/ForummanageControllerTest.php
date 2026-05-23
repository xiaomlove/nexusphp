<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for `App\Http\Controllers\Legacy\ForummanageController`
 * (replaces `public/forummanage.php`).
 */
class ForummanageControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_URI'] = '/forummanage.php';
    }

    public function test_guest_redirects_to_login(): void
    {
        $response = $this->get('/forummanage.php');

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_user_without_forummanage_is_forbidden(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/forummanage.php');

        $response->assertForbidden();
    }

    public function test_admin_sees_forum_list_with_buttons(): void
    {
        $admin = $this->createLegacyUser(overrides: ['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/forummanage.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        // Both action buttons must be present.
        $this->assertStringContainsString('action="/moforums.php"', $body);
        $this->assertStringContainsString('action="/forummanage.php"', $body);
        $this->assertStringContainsString('name="action" value="newforum"', $body);
    }

    public function test_newforum_action_renders_create_form(): void
    {
        $admin = $this->createLegacyUser(overrides: ['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/forummanage.php?action=newforum');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<form method="post" action="/forummanage.php">', $body);
        $this->assertStringContainsString('name="action" value="addforum"', $body);
    }

    public function test_editforum_with_zero_id_renders_no_records(): void
    {
        $admin = $this->createLegacyUser(overrides: ['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/forummanage.php?action=editforum&id=0');

        $response->assertOk();
        // Either "No records found" or no edit-form fields rendered.
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('name="action" value="editforum"', $body);
    }

    public function test_empty_addforum_post_redirects(): void
    {
        $admin = $this->createLegacyUser(overrides: ['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->post('/forummanage.php', [
            'action' => 'addforum',
            'name' => '',
            'desc' => '',
        ]);

        $response->assertRedirect('/forummanage.php');
    }

    public function test_del_with_zero_id_redirects_without_deleting(): void
    {
        $admin = $this->createLegacyUser(overrides: ['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/forummanage.php?action=del&id=0');

        $response->assertRedirect('/forummanage.php');
    }
}
