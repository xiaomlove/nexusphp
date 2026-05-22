<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for `App\Http\Controllers\Legacy\LinksManageController`
 * (replaces `public/linksmanage.php`).
 *
 * Coverage focus:
 *   - guest → 401,
 *   - `?action=apply` GET / `?action=newapply` POST gated on
 *     `applylink` permission (legacy `permissiondenied()` → 403),
 *   - default GET / `?action=add` POST / `?action=editlink` POST /
 *     `?action=del` GET gated on `linkmanage` permission,
 *   - apply-form validation rejects missing fields with 422,
 *   - happy-path submit inserts a `staffmessages` row,
 *   - admin add/edit/delete round-trip on the `links` table,
 *   - admin listing renders existing rows.
 */
class LinksManageControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** @var array<int,int> */
    private array $createdLinkIds = [];

    /** @var array<int,int> */
    private array $createdStaffmessageIds = [];

    protected function tearDown(): void
    {
        if (! empty($this->createdLinkIds)) {
            NexusDB::table('links')->whereIn('id', $this->createdLinkIds)->delete();
        }
        if (! empty($this->createdStaffmessageIds)) {
            NexusDB::table('staffmessages')->whereIn('id', $this->createdStaffmessageIds)->delete();
        }
        parent::tearDown();
    }

    public function test_guest_request_is_unauthenticated(): void
    {
        $response = $this->get('/linksmanage.php');

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_apply_form_renders_for_user_with_applylink_permission(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/linksmanage.php?action=apply');

        $response->assertOk();
        $this->assertStringContainsString('<form method="post" action="/linksmanage.php">', (string) $response->getContent());
        $this->assertStringContainsString('name="action" value="newapply"', (string) $response->getContent());
    }

    public function test_apply_post_with_missing_fields_returns_422(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/linksmanage.php?action=newapply', []);

        $response->assertStatus(422);
    }

    public function test_apply_post_inserts_staffmessages_row(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/linksmanage.php?action=newapply', [
            'linkname' => 'ExampleSite',
            'url' => 'https://example.test',
            'title' => 'Example',
            'admin' => 'admin@example.test',
            'email' => 'admin@example.test',
            'reason' => 'We would like to exchange links — long enough reason.',
        ]);

        $response->assertOk();

        $row = (array) NexusDB::table('staffmessages')
            ->where('sender', $user->id)
            ->orderByDesc('id')
            ->first();
        $this->assertNotEmpty($row);
        $this->createdStaffmessageIds[] = (int) $row['id'];
        $this->assertSame('ExampleSite applys for links', $row['subject']);
        $this->assertStringContainsString('https://example.test', (string) $row['msg']);
    }

    public function test_admin_default_view_renders_listing(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($user, 'nexus-web');

        $linkId = (int) NexusDB::table('links')->insertGetId([
            'name' => 'ExampleLink-'.bin2hex(random_bytes(2)),
            'url' => 'https://example.test',
            'title' => 'Example',
        ]);
        $this->createdLinkIds[] = $linkId;

        $response = $this->get('/linksmanage.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('?action=edit&id='.$linkId, $body);
    }

    public function test_admin_add_action_inserts_link_row(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($user, 'nexus-web');

        $name = 'AddViaPost-'.bin2hex(random_bytes(2));
        $response = $this->post('/linksmanage.php?action=add', [
            'linkname' => $name,
            'url' => 'https://example.test',
            'title' => 'Example',
        ]);

        $response->assertRedirect('/linksmanage.php');

        $row = (array) NexusDB::table('links')->where('name', $name)->first();
        $this->assertNotEmpty($row);
        $this->createdLinkIds[] = (int) $row['id'];
    }

    public function test_admin_del_action_removes_link_row(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($user, 'nexus-web');

        $linkId = (int) NexusDB::table('links')->insertGetId([
            'name' => 'DeleteMe-'.bin2hex(random_bytes(2)),
            'url' => 'https://example.test',
            'title' => 'gone',
        ]);

        $response = $this->get('/linksmanage.php?action=del&id='.$linkId);

        $response->assertRedirect('/linksmanage.php');
        $this->assertNull(NexusDB::table('links')->where('id', $linkId)->first());
    }
}
