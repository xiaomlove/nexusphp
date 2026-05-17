<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/unco.php` contract.
 *
 * Moderator+ listing of `users.status='pending'` rows, ordered by
 * `username`. Each row renders a tiny `<form>` posting to
 * `modtask.php?action=confirmuser` (which is still legacy and lives
 * unchanged). The optional `?status=...` query is a flag set by
 * `modtask.php` after a successful confirm/reject and only controls
 * a banner — it is not used as an identifier.
 */
class UncoControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/unco.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/unco.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_below_moderator_is_forbidden(): void
    {
        $user = $this->createConfirmedUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/unco.php')->assertForbidden();
    }

    public function test_no_pending_users_renders_nothing_found_notice(): void
    {
        $mod = $this->createConfirmedUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($mod, 'nexus-web');
        $this->purgePendingUsers();

        $response = $this->get('/unco.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Unconfirmed Users</title>', $body);
        $this->assertStringContainsString('Nothing Found', $body);
        $this->assertStringNotContainsString('The user account has been updated', $body);
    }

    public function test_status_flag_with_no_pending_users_renders_updated_notice(): void
    {
        $mod = $this->createConfirmedUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($mod, 'nexus-web');
        $this->purgePendingUsers();

        $response = $this->get('/unco.php?status=1');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('The user account has been updated', $body);
        $this->assertStringNotContainsString('Nothing Found', $body);
    }

    public function test_pending_users_render_table_with_modtask_forms(): void
    {
        $mod = $this->createConfirmedUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($mod, 'nexus-web');
        $this->purgePendingUsers();

        $pending = $this->createForcedPendingUser([
            'username' => 'pendinguser_'.bin2hex(random_bytes(3)),
            'email' => 'pending@example.test',
        ]);

        $response = $this->get('/unco.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString($pending->username, $body);
        $this->assertStringContainsString('pending@example.test', $body);
        $this->assertStringContainsString('action="modtask.php"', $body);
        $this->assertStringContainsString('name="action" value="confirmuser"', $body);
        $this->assertStringContainsString('name="userid" value="'.$pending->id.'"', $body);
        $this->assertStringContainsString('href="userdetails.php?id='.$pending->id.'"', $body);
        $this->assertStringContainsString('<option value="pending">pending</option>', $body);
        $this->assertStringContainsString('<option value="confirmed">confirmed</option>', $body);
    }

    public function test_status_flag_with_pending_users_renders_banner_above_table(): void
    {
        $mod = $this->createConfirmedUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($mod, 'nexus-web');
        $this->purgePendingUsers();

        $pending = $this->createForcedPendingUser();

        $response = $this->get('/unco.php?status=1');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('The User account has been updated', $body);
        $this->assertStringContainsString($pending->username, $body);
    }

    public function test_confirmed_users_are_filtered_out(): void
    {
        $mod = $this->createConfirmedUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($mod, 'nexus-web');
        $this->purgePendingUsers();

        $confirmed = $this->createConfirmedUser([
            'username' => 'visible_confirmed_'.bin2hex(random_bytes(3)),
        ]);

        $response = $this->get('/unco.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Nothing Found', $body);
        $this->assertStringNotContainsString($confirmed->username, $body);
    }

    public function test_html_in_username_is_escaped(): void
    {
        $mod = $this->createConfirmedUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($mod, 'nexus-web');
        $this->purgePendingUsers();

        $pending = $this->createForcedPendingUser([
            'username' => '<script>alert(1)</script>',
        ]);

        $response = $this->get('/unco.php');

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        $this->assertStringContainsString('userdetails.php?id='.$pending->id, $body);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createConfirmedUser(array $overrides = []): User
    {
        $user = $this->createLegacyUser(
            overrides: array_merge(
                ['lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );
        NexusDB::table('users')
            ->where('id', $user->id)
            ->update(['status' => User::STATUS_CONFIRMED]);
        $user->status = User::STATUS_CONFIRMED;

        return $user;
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createForcedPendingUser(array $overrides = []): User
    {
        $user = $this->createPendingUser(overrides: array_merge(
            ['lang' => self::ENGLISH_LANGUAGE_ID],
            $overrides,
        ));
        NexusDB::table('users')
            ->where('id', $user->id)
            ->update(['status' => User::STATUS_PENDING]);
        $user->status = User::STATUS_PENDING;

        return $user;
    }

    private function purgePendingUsers(): void
    {
        NexusDB::table('users')
            ->where('status', User::STATUS_PENDING)
            ->delete();
    }
}
