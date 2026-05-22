<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for `App\Http\Controllers\Legacy\StaffController`
 * (replaces `public/staff.php`).
 */
class StaffControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/staff.php');

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_below_staffmem_class_is_forbidden(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/staff.php');

        $response->assertForbidden();
    }

    public function test_staff_user_sees_all_five_section_headers(): void
    {
        $staff = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staff, 'nexus-web');

        $response = $this->get('/staff.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        // En lang_staff strings — assert each section header lands.
        $this->assertStringContainsString('<title>Staff</title>', $body);
        $this->assertStringContainsString('Firstline Support', $body);
        $this->assertStringContainsString('Critics', $body);
        $this->assertStringContainsString('Forum Moderators', $body);
        $this->assertStringContainsString('General Staff', $body);
        $this->assertStringContainsString('VIP', $body);
    }
}
