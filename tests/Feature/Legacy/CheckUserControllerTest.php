<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/checkuser.php` contract.
 *
 * Renders details of a pending account; reachable by the inviter
 * (`users.invited_by == $CURUSER['id']`) or any moderator+. The
 * embedded `<form action="takeconfirm.php">` posts to the already
 * migrated `/takeconfirm.php` endpoint.
 */
class CheckUserControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/checkuser.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/checkuser.php?id=1');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_missing_id_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/checkuser.php');

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'Invalid user id.',
            (string) $response->getContent(),
        );
    }

    public function test_zero_id_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/checkuser.php?id=0')->assertStatus(422);
    }

    public function test_nonexistent_id_returns_404(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/checkuser.php?id=99999999');

        $response->assertStatus(404);
        $this->assertStringContainsString(
            'No such user.',
            (string) $response->getContent(),
        );
    }

    public function test_confirmed_user_returns_404(): void
    {
        $confirmed = $this->createTestUser(['status' => User::STATUS_CONFIRMED]);

        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/checkuser.php?id='.$confirmed->id)->assertStatus(404);
    }

    public function test_unprivileged_non_inviter_is_forbidden(): void
    {
        $inviter = $this->createTestUser();
        $pending = $this->createForcedPendingUser(invitedBy: $inviter->id);

        $stranger = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($stranger, 'nexus-web');

        $this->get('/checkuser.php?id='.$pending->id)->assertForbidden();
    }

    public function test_inviter_can_view_pending_invitee(): void
    {
        $inviter = $this->createTestUser(['class' => User::CLASS_USER]);
        $pending = $this->createForcedPendingUser(
            overrides: [
                'username' => 'invitee_'.bin2hex(random_bytes(3)),
                'email' => 'invitee@example.test',
            ],
            invitedBy: $inviter->id,
            extra: ['gender' => 'Male'],
        );

        $this->actingAs($inviter, 'nexus-web');

        $response = $this->get('/checkuser.php?id='.$pending->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString($pending->username, $body);
        $this->assertStringContainsString('invitee@example.test', $body);
        $this->assertStringContainsString('action="takeconfirm.php?id='.$pending->id.'"', $body);
        $this->assertStringContainsString('name="conusr[]" value="'.$pending->id.'"', $body);
        $this->assertStringContainsString('Confirm this user', $body);
    }

    public function test_moderator_can_view_any_pending_user_and_sees_ip(): void
    {
        $pending = $this->createForcedPendingUser(
            overrides: [
                'username' => 'pmod_'.bin2hex(random_bytes(3)),
                'email' => 'pmod@example.test',
            ],
            invitedBy: 0,
            extra: ['ip' => '203.0.113.99'],
        );

        $moderator = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($moderator, 'nexus-web');

        $response = $this->get('/checkuser.php?id='.$pending->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString($pending->username, $body);
        $this->assertStringContainsString('203.0.113.99', $body);
    }

    public function test_unprivileged_inviter_does_not_see_ip_field(): void
    {
        $inviter = $this->createTestUser(['class' => User::CLASS_USER]);
        $pending = $this->createForcedPendingUser(
            invitedBy: $inviter->id,
            extra: ['ip' => '203.0.113.55'],
        );

        $this->actingAs($inviter, 'nexus-web');

        $body = (string) $this->get('/checkuser.php?id='.$pending->id)->getContent();
        $this->assertStringNotContainsString('203.0.113.55', $body);
        $this->assertStringNotContainsString('<td class="rowhead" width="1%">IP</td>', $body);
    }

    public function test_html_in_username_is_escaped(): void
    {
        $inviter = $this->createTestUser();
        $pending = $this->createForcedPendingUser(
            overrides: ['username' => '<script>alert(1)</script>'],
            invitedBy: $inviter->id,
        );

        $this->actingAs($inviter, 'nexus-web');

        $body = (string) $this->get('/checkuser.php?id='.$pending->id)->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function test_disabled_pending_user_renders_disabled_notice(): void
    {
        $inviter = $this->createTestUser();
        $pending = $this->createForcedPendingUser(
            invitedBy: $inviter->id,
            extra: ['enabled' => 'no'],
        );

        $this->actingAs($inviter, 'nexus-web');

        $body = (string) $this->get('/checkuser.php?id='.$pending->id)->getContent();
        $this->assertStringContainsString('This account is disabled.', $body);
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

    /**
     * @param  array<string,mixed>  $overrides
     * @param  array<string,mixed>  $extra  Columns not in the User
     *                                      model's `$fillable` list
     *                                      (e.g. `ip`, `gender`,
     *                                      `enabled='no'`).
     */
    private function createForcedPendingUser(
        array $overrides = [],
        int $invitedBy = 0,
        array $extra = [],
    ): User {
        $user = $this->createPendingUser(overrides: array_merge(
            ['lang' => self::ENGLISH_LANGUAGE_ID],
            $overrides,
        ));
        NexusDB::table('users')
            ->where('id', $user->id)
            ->update(array_merge(
                [
                    'status' => User::STATUS_PENDING,
                    'invited_by' => $invitedBy,
                ],
                $extra,
            ));
        $user->status = User::STATUS_PENDING;

        return $user;
    }
}
