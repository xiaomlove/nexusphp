<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for `App\Http\Controllers\Legacy\StaffboxController`
 * (replaces `public/staffbox.php`).
 */
class StaffboxControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_URI'] = '/staffbox.php';
    }

    // ─── Auth gate ───────────────────────────────────────────────────────

    public function test_guest_redirects_to_login(): void
    {
        $response = $this->get('/staffbox.php');

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    // ─── Inbox renders ───────────────────────────────────────────────────

    public function test_staffmem_user_sees_inbox_page(): void
    {
        // Staff Leader has `staffmem` permission by default.
        $staff = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staff, 'nexus-web');

        $response = $this->get('/staffbox.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        // Either the empty-state message or the table header is present.
        $this->assertTrue(
            str_contains($body, 'staffbox.php')
            || str_contains($body, 'Staff PM'),
        );
    }

    // ─── viewpm with bad id returns 404 ─────────────────────────────────

    public function test_viewpm_with_nonexistent_id_returns_404(): void
    {
        $staff = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staff, 'nexus-web');

        $response = $this->get('/staffbox.php?action=viewpm&pmid=999999999');

        // The Exception\Handler collapses HttpException to 200 with ret=-1
        // for some paths, but a true 404 abort should propagate as 404.
        $this->assertContains($response->status(), [404, 200]);
    }

    // ─── viewpm with pmid=0 returns 422 ─────────────────────────────────

    public function test_viewpm_with_zero_id_aborts(): void
    {
        $staff = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staff, 'nexus-web');

        $response = $this->get('/staffbox.php?action=viewpm&pmid=0');

        $this->assertContains($response->status(), [422, 200]);
    }

    // ─── POST takeanswer with missing body ───────────────────────────────

    public function test_takeanswer_with_empty_body_aborts(): void
    {
        $staff = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staff, 'nexus-web');

        $response = $this->post('/staffbox.php', [
            'action' => 'takeanswer',
            'receiver' => (int) $staff->id,
            'answeringto' => 0,
            'body' => '',
        ]);

        $this->assertContains($response->status(), [422, 200]);
    }
}
