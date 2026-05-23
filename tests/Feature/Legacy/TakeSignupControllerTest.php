<?php

namespace Tests\Feature\Legacy;

use App\Models\Invite;
use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `POST /takesignup.php` contract introduced by the
 * Phase 2 migration of `public/takesignup.php` (auth-flow batch
 * part 2 of 3 — see `SignupController` PHPDoc).
 *
 * Test posture
 * ------------
 * Bark / validation paths return HTTP 200 with the legacy chrome
 * envelope (or, when the legacy chrome is not bootstrapped in the
 * test runner, the chrome-less fallback the controller renders
 * instead). All bark assertions look for the message text
 * regardless of which envelope is rendered.
 *
 * Happy path is deliberately NOT covered by this test class. The
 * legacy `users` row insert pulls in `$defaultclass_class`,
 * `$defcss`, `$iniupload_main`, `$invite_count`, and `$verification`
 * globals that are seeded by `include/bittorrent.php` at runtime
 * but not by the test bootstrap. The full user-create flow is
 * exercised by the existing E2E `signup` smoke spec in CI.
 */
class TakeSignupControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/takesignup.php';
    }

    public function test_already_logged_in_user_is_redirected_to_index(): void
    {
        // cur_user_check() parity — the controller does not even
        // try to validate the payload when there's already a
        // session. Avoids creating a duplicate user via a stray
        // double-submit from a freshly-confirmed account.
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takesignup.php', [
            'wantusername' => 'newusertest',
            'wantpassword' => 'p4ssw0rd-test',
            'email' => 'newuser@example.test',
        ]);

        $response->assertRedirect('/index.php');
    }

    public function test_ip_banned_request_aborts_with_403(): void
    {
        $maxAttempts = 5;
        $GLOBALS['maxloginattempts'] = $maxAttempts;

        try {
            $ip = '127.0.0.1';
            NexusDB::insert('loginattempts', [
                'ip' => $ip,
                'added' => date('Y-m-d H:i:s'),
                'attempts' => $maxAttempts + 1,
            ]);

            $response = $this->post('/takesignup.php', [
                'wantusername' => 'whatever',
                'wantpassword' => 'whatever',
                'email' => 'whatever@example.test',
            ]);

            $response->assertForbidden();
        } finally {
            unset($GLOBALS['maxloginattempts']);
        }
    }

    public function test_missing_username_returns_bark(): void
    {
        $response = $this->post('/takesignup.php', [
            'wantpassword' => 'whatever',
            'email' => 'someone@example.test',
            'country' => '1',
            'gender' => 'Male',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        // Either the legacy `stdmsg` envelope or the chrome-less
        // fallback is acceptable; both should include the failure
        // heading.
        $this->assertStringContainsString('ignup', $body);
    }

    public function test_invalid_gender_returns_bark(): void
    {
        $response = $this->post('/takesignup.php', [
            'wantusername' => 'gendertest',
            'wantpassword' => 'whatever',
            'email' => 'gendertest@example.test',
            'country' => '1',
            'gender' => 'NotARealGender',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        // The bark body should mention "gender" — verbatim from
        // the legacy lang dictionary or the controller's English
        // fallback.
        $this->assertStringContainsString('gender', strtolower($body));
    }

    public function test_invite_mode_with_unknown_hash_returns_bark(): void
    {
        $response = $this->post('/takesignup.php', [
            'type' => 'invite',
            'inviter' => '1',
            'hash' => 'no-such-invite-'.bin2hex(random_bytes(8)),
            'wantusername' => 'invitee',
            'wantpassword' => 'p4ssw0rd-test',
            'email' => 'invitee@example.test',
            'country' => '1',
            'gender' => 'Female',
            'rulesverify' => 'yes',
            'faqverify' => 'yes',
            'ageverify' => 'yes',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('invalid', strtolower($body));
    }

    public function test_invite_mode_with_inviter_mismatch_invalidates_invite_and_barks(): void
    {
        $inviter = $this->createTestUser();
        $imposter = $this->createTestUser();

        $hash = bin2hex(random_bytes(16));
        $inviteId = NexusDB::insert('invites', [
            'inviter' => (int) $inviter->id,
            'hash' => $hash,
            'valid' => Invite::VALID_YES,
            'time_invited' => now()->toDateTimeString(),
        ]);

        // POST claims the invite belongs to a DIFFERENT inviter
        // (the imposter). The controller must refuse the registration
        // AND mark the invite invalid (anti-stuffing).
        $response = $this->post('/takesignup.php', [
            'type' => 'invite',
            'inviter' => (string) $imposter->id,
            'hash' => $hash,
            'wantusername' => 'invitee',
            'wantpassword' => 'p4ssw0rd-test',
            'email' => 'invitee@example.test',
            'country' => '1',
            'gender' => 'Female',
            'rulesverify' => 'yes',
            'faqverify' => 'yes',
            'ageverify' => 'yes',
        ]);

        $response->assertOk();

        // Invite row should now be flipped to `valid = NO`.
        $row = NexusDB::table('invites')
            ->where('hash', $hash)
            ->select(['valid'])
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(
            (int) Invite::VALID_NO,
            (int) ((array) $row)['valid'],
            'Invite must be invalidated on inviter mismatch (anti-stuffing)',
        );
    }

    public function test_existing_email_returns_bark(): void
    {
        $existing = $this->createTestUser();

        $response = $this->post('/takesignup.php', [
            'wantusername' => 'newunique',
            'wantpassword' => 'p4ssw0rd-test',
            'email' => $existing->email,
            'country' => '1',
            'gender' => 'Male',
            'rulesverify' => 'yes',
            'faqverify' => 'yes',
            'ageverify' => 'yes',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString($existing->email, $body);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(overrides: array_merge([
            'lang' => self::ENGLISH_LANGUAGE_ID,
        ], $overrides));
    }
}
