<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/delacctadmin.php` contract.
 *
 * The `user-delete` permission is class-derived (see
 * `ToolRepository::listUserClassPermissions`); in the seeded test
 * database it maps to STAFF_LEADER and above. We use STAFF_LEADER
 * for the happy-path tests because `user_can()` short-circuits to
 * `true` for that class regardless of the seed state.
 */
class DelAcctAdminControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/delacctadmin.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/delacctadmin.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location')
        );
    }

    public function test_user_without_user_delete_permission_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/delacctadmin.php')->assertForbidden();
        $this->post('/delacctadmin.php', ['userid' => '1'])->assertForbidden();
    }

    public function test_authorized_get_renders_form(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/delacctadmin.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Delete account', $body);
        $this->assertStringContainsString('action="delacctadmin.php"', $body);
        $this->assertStringContainsString('name="userid"', $body);
    }

    public function test_authorized_post_with_empty_userid_shows_error(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->post('/delacctadmin.php', ['userid' => '']);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Please fill out the form correctly.', $body);
        $this->assertStringContainsString('name="userid"', $body);
    }

    public function test_authorized_post_with_unknown_userid_shows_error(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->post('/delacctadmin.php', ['userid' => '999999999']);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Bad user id', $body);
    }

    public function test_authorized_post_with_valid_userid_deletes_account(): void
    {
        $target = $this->createTestUser();
        $targetName = (string) $target->username;
        $targetId = (int) $target->id;

        $admin = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->post('/delacctadmin.php', ['userid' => (string) $targetId]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString(
            'The account <b>'.htmlspecialchars($targetName).'</b> was deleted.',
            $body,
        );

        // `UserRepository::destroy` may soft-delete or hard-delete
        // depending on the system configuration. Either way, the row
        // should no longer come back from the canonical `id`
        // lookup with the original `enabled` state.
        $row = NexusDB::table('users')->where('id', $targetId)->first();
        $row = $row ? (array) $row : null;
        if ($row !== null) {
            $this->assertNotSame(
                $targetName,
                (string) ($row['username'] ?? ''),
                'destroy() should rename/anonymize the deleted account if it does not hard-delete',
            );
        }
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
