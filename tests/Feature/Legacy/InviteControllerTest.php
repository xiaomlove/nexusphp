<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for `App\Http\Controllers\Legacy\InviteController`
 * (replaces `public/invite.php`).
 */
class InviteControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_URI'] = '/invite.php';
    }

    public function test_guest_redirects_to_login(): void
    {
        $response = $this->get('/invite.php?id=1');

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_user_viewing_other_profile_without_permission_is_forbidden(): void
    {
        $viewer = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $target = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($viewer, 'nexus-web');

        $response = $this->get('/invite.php?id='.$target->id);

        $response->assertForbidden();
    }

    public function test_invalid_id_is_forbidden(): void
    {
        $viewer = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($viewer, 'nexus-web');

        $response = $this->get('/invite.php?id=0');

        // is_valid_id(0) === false → 403 (legacy permission gate combined invalid-id and permission).
        $response->assertForbidden();
    }

    public function test_owner_sees_invitee_menu_by_default(): void
    {
        $owner = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($owner, 'nexus-web');

        $response = $this->get('/invite.php?id='.$owner->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('id="invitemenu"', $body);
        $this->assertStringContainsString('menu=invitee', $body);
        $this->assertStringContainsString('menu=sent', $body);
        $this->assertStringContainsString('menu=tmp', $body);
    }

    public function test_owner_sees_sent_menu(): void
    {
        $owner = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($owner, 'nexus-web');

        $response = $this->get('/invite.php?id='.$owner->id.'&menu=sent');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('id="invitemenu"', $body);
    }

    public function test_owner_sees_tmp_menu(): void
    {
        $owner = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($owner, 'nexus-web');

        $response = $this->get('/invite.php?id='.$owner->id.'&menu=tmp');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('id="invitemenu"', $body);
    }

    public function test_admin_can_view_other_user_invites(): void
    {
        $admin = $this->createLegacyUser(overrides: ['class' => User::CLASS_ADMINISTRATOR]);
        $target = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/invite.php?id='.$target->id);

        // Admin has viewinvite permission.
        $response->assertOk();
    }
}
