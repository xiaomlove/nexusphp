<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use App\Repositories\ToolRepository;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/takeconfirm.php` contract.
 *
 * The "I as inviter want to confirm a list of users I invited" POST
 * endpoint. Legacy script: `$CURUSER['id'] == $id || user_can('viewinvite')`
 * → confirm the users → send a "your account has been confirmed"
 * email → 302 back to `/invite.php?id=<inviter_id>`. We preserve the
 * same shape:
 *   - Guest → login redirect.
 *   - Missing / non-positive `id` → 422.
 *   - Acting user is neither the inviter nor has `viewinvite` → 403
 *     (legacy `stderr()` rendered 200).
 *   - No `conusr[]` or no matching pending invitees → 200 "no buddy
 *     to confirm".
 *   - Happy path → flip `users.status='confirmed' / editsecret=''`
 *     for the matched rows, fire `ToolRepository::sendMail` once,
 *     302 to `/invite.php?id=<inviter_id>`.
 *
 * We stub `ToolRepository` in the Laravel container so the test
 * doesn't actually talk to an SMTP server.
 */
class TakeConfirmControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/takeconfirm.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->post('/takeconfirm.php', ['id' => 1]);

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

        $this->post('/takeconfirm.php', [])->assertStatus(422);
    }

    public function test_third_party_without_viewinvite_is_forbidden(): void
    {
        $inviter = $this->createTestUser();
        $other = $this->createTestUser();
        $this->actingAs($other, 'nexus-web');

        $this->post(
            '/takeconfirm.php?id='.$inviter->id,
            ['conusr' => [1], 'email' => 'whoever@example.test'],
        )->assertForbidden();
    }

    public function test_self_inviter_can_post_without_conusr_and_sees_no_buddy_notice(): void
    {
        $inviter = $this->createTestUser();
        $this->actingAs($inviter, 'nexus-web');

        $stub = $this->createMock(ToolRepository::class);
        $stub->expects($this->never())->method('sendMail');
        $this->app->instance(ToolRepository::class, $stub);

        $response = $this->post('/takeconfirm.php', ['id' => $inviter->id]);

        $response->assertOk();
        $this->assertStringContainsString(
            'No buddy to confirm',
            (string) $response->getContent(),
        );
    }

    public function test_invitees_unrelated_to_inviter_are_ignored_with_no_buddy_notice(): void
    {
        $inviter = $this->createTestUser();
        $this->actingAs($inviter, 'nexus-web');

        // A pending user, but invited by someone else — `WHERE
        // invited_by = $id` filters them out.
        $someoneElse = $this->createTestUser();
        $pending = $this->createPendingUser(overrides: [
            'invited_by' => $someoneElse->id,
            'lang' => self::ENGLISH_LANGUAGE_ID,
        ]);

        $stub = $this->createMock(ToolRepository::class);
        $stub->expects($this->never())->method('sendMail');
        $this->app->instance(ToolRepository::class, $stub);

        $response = $this->post('/takeconfirm.php', [
            'id' => $inviter->id,
            'conusr' => [$pending->id],
            'email' => 'pending@example.test',
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'No buddy to confirm',
            (string) $response->getContent(),
        );

        // The unrelated pending user must NOT be flipped to confirmed.
        $this->assertSame(
            User::STATUS_PENDING,
            (string) User::query()->where('id', $pending->id)->value('status'),
        );
    }

    public function test_happy_path_confirms_pending_users_sends_mail_and_redirects(): void
    {
        $inviter = $this->createTestUser();
        $this->actingAs($inviter, 'nexus-web');

        $pendingOne = $this->createPendingUser(overrides: [
            'invited_by' => $inviter->id,
            'editsecret' => 'pending-secret-1',
            'lang' => self::ENGLISH_LANGUAGE_ID,
        ]);
        $pendingTwo = $this->createPendingUser(overrides: [
            'invited_by' => $inviter->id,
            'editsecret' => 'pending-secret-2',
            'lang' => self::ENGLISH_LANGUAGE_ID,
        ]);

        $stub = $this->createMock(ToolRepository::class);
        $stub->expects($this->once())
            ->method('sendMail')
            ->with(
                'newbie@example.test',
                $this->stringContains('Confirmed'),
                $this->isType('string'),
                true,
            )
            ->willReturn(true);
        $this->app->instance(ToolRepository::class, $stub);

        $response = $this->post('/takeconfirm.php?id='.$inviter->id, [
            'conusr' => [$pendingOne->id, $pendingTwo->id],
            'email' => 'newbie@example.test',
        ]);

        $response->assertRedirect('/invite.php?id='.$inviter->id);

        $pendingOne->refresh();
        $pendingTwo->refresh();
        $this->assertSame(User::STATUS_CONFIRMED, $pendingOne->status);
        $this->assertSame(User::STATUS_CONFIRMED, $pendingTwo->status);
        $this->assertSame('', (string) $pendingOne->editsecret);
        $this->assertSame('', (string) $pendingTwo->editsecret);
    }

    public function test_mail_send_failure_is_swallowed_and_still_confirms_users(): void
    {
        $inviter = $this->createTestUser();
        $this->actingAs($inviter, 'nexus-web');

        $pending = $this->createPendingUser(overrides: [
            'invited_by' => $inviter->id,
            'lang' => self::ENGLISH_LANGUAGE_ID,
        ]);

        $stub = $this->createMock(ToolRepository::class);
        $stub->expects($this->once())
            ->method('sendMail')
            ->willThrowException(new \RuntimeException('SMTP refused'));
        $this->app->instance(ToolRepository::class, $stub);

        $response = $this->post('/takeconfirm.php', [
            'id' => $inviter->id,
            'conusr' => [$pending->id],
            'email' => 'newbie@example.test',
        ]);

        // Mail failure does NOT roll back the confirm flip — same as
        // legacy `sent_mail(..., false, ...)` which `do_log()`'d the
        // failure and let the redirect happen.
        $response->assertRedirect('/invite.php?id='.$inviter->id);

        $pending->refresh();
        $this->assertSame(User::STATUS_CONFIRMED, $pending->status);
    }

    public function test_viewinvite_holder_can_confirm_for_another_inviter(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($admin, 'nexus-web');

        $inviter = $this->createTestUser();
        $pending = $this->createPendingUser(overrides: [
            'invited_by' => $inviter->id,
            'lang' => self::ENGLISH_LANGUAGE_ID,
        ]);

        $stub = $this->createMock(ToolRepository::class);
        $stub->expects($this->once())
            ->method('sendMail')
            ->willReturn(true);
        $this->app->instance(ToolRepository::class, $stub);

        $response = $this->post('/takeconfirm.php?id='.$inviter->id, [
            'conusr' => [$pending->id],
            'email' => 'newbie@example.test',
        ]);

        // Redirect target is `$user->id` (acting user), not `$id`
        // (inviter) — matches the legacy
        // `header("Location: invite.php?id=".htmlspecialchars($CURUSER['id']))`.
        $response->assertRedirect('/invite.php?id='.$admin->id);

        $pending->refresh();
        $this->assertSame(User::STATUS_CONFIRMED, $pending->status);
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
