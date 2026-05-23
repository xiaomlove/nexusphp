<?php

namespace Tests\Feature\Legacy;

use App\Models\Invite;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/takeinvite.php` POST contract introduced by the
 * Phase 2 migration of `public/takeinvite.php`. The endpoint is the
 * write-handler of the `?type=new` form rendered by
 * {@see \App\Http\Controllers\Legacy\InviteController::renderNewForm()}.
 *
 * Test posture
 * ------------
 * Bark / validation paths return HTTP 200 with the legacy
 * `stdmsg()` envelope (or, when the legacy chrome is not bootstrapped
 * in the test runner, the chrome-less fallback the controller renders
 * instead). All bark assertions look for the message text regardless
 * of which envelope the response body uses.
 *
 * Happy path is deliberately NOT covered here. The legacy
 * `sent_mail()` global talks to a configured SMTP server, the
 * mail-success path then INSERTs into `invites` and decrements
 * `users.invites`, and we cannot reliably stub `sent_mail()` from a
 * Feature test. Same caveat as `TakeUploadControllerTest` (see PR
 * #302); the contract is exercised by the existing E2E smoke spec
 * in CI.
 *
 * Permission-test users
 * ---------------------
 * `App\Models\User::CLASS_STAFF_LEADER` short-circuits
 * `user_can(...)` to `true` (see
 * `include/globalfunctions.php::user_can` line 1297), so we use it
 * to bypass the `sendinvite` permission gate without seeding an
 * authority row. Pairing that with a non-zero `users.invites` count
 * and `Setting::set('main.invitesystem', 'yes')` lets the controller
 * fall through `UserRepository::getInviteBtnText()` into the field
 * validation chain we want to pin.
 */
class TakeInviteControllerTest extends FeatureTestCase
{
    /** `language.id` for English in the seeded `language` table. */
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/takeinvite.php';

        // Make sure the invite system is on for tests that exercise
        // the field-validation chain. The controller calls
        // `registration_check('invitesystem', true, false)` which
        // `stderr()`-exits when this is `'no'`.
        $this->setSetting('main.invitesystem', 'yes');
    }

    public function test_guest_post_redirects_to_login(): void
    {
        $response = $this->post('/takeinvite.php', []);

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_parked_user_is_forbidden(): void
    {
        $user = $this->createPrivilegedUser(['parked' => 'yes']);
        $this->actingAs($user, 'nexus-web');

        $this->post('/takeinvite.php', [
            'email' => 'newbie@example.test',
            'body' => 'Hello!',
            'hash' => 'permanent',
        ])->assertForbidden();
    }

    public function test_user_without_invite_quota_sees_bark_from_get_invite_btn_text(): void
    {
        // Default `CLASS_USER` user with `invites=0` — the
        // `UserRepository::getInviteBtnText()` call that the
        // controller makes BEFORE field validation throws
        // `NexusException`, and the controller barks with that
        // message. The exact message depends on whether the
        // permission or quota gate fires first; here we assert that
        // the response body contains the legacy "Invitation failed"
        // heading + the response is HTTP 200 (bark envelope).
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takeinvite.php', [
            'email' => 'newbie@example.test',
            'body' => 'Hello!',
            'hash' => 'permanent',
        ]);

        $response->assertOk();
        // The user has no `sendinvite` permission and no invite
        // quota — `getInviteBtnText` raises a `NexusException` whose
        // localised message is in the response body. We only assert
        // the bark envelope; the exact phrasing is locale-dependent.
        $this->assertBarkResponse($response);
    }

    public function test_missing_email_barks_must_enter_email(): void
    {
        $user = $this->createPrivilegedUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takeinvite.php', [
            'email' => '',
            'body' => 'Hello!',
            'hash' => 'permanent',
        ]);

        $response->assertOk();
        $this->assertBarkContains(
            $response,
            'You must enter an email address!',
        );
    }

    public function test_invalid_email_format_barks_invalid_email(): void
    {
        $user = $this->createPrivilegedUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takeinvite.php', [
            'email' => 'not-a-valid-email',
            'body' => 'Hello!',
            'hash' => 'permanent',
        ]);

        $response->assertOk();
        $this->assertBarkContains($response, 'Invalid email address!');
    }

    public function test_pre_register_username_too_long_barks(): void
    {
        // The legacy length check (`strlen > 12`) runs unconditionally
        // — independent of the `is_invite_pre_email_and_username`
        // setting — so we can pin it without flipping the toggle.
        $user = $this->createPrivilegedUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takeinvite.php', [
            'email' => 'newbie@example.test',
            'body' => 'Hello!',
            'hash' => 'permanent',
            'pre_register_username' => 'this_username_is_too_long',
        ]);

        $response->assertOk();
        $this->assertBarkContains(
            $response,
            'username is too long',
        );
    }

    public function test_missing_personal_message_barks(): void
    {
        $user = $this->createPrivilegedUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takeinvite.php', [
            'email' => 'newbie@example.test',
            'body' => '',
            'hash' => 'permanent',
        ]);

        $response->assertOk();
        $this->assertBarkContains(
            $response,
            'Please add a personal message.',
        );
    }

    public function test_body_with_only_html_tags_strips_to_empty_and_barks(): void
    {
        // The legacy `strip_tags(trim($body))` reduces this payload
        // to `''`, which then trips the empty-message bark. Pinning
        // the contract guards against an inadvertent switch to a
        // less-strict sanitiser.
        $user = $this->createPrivilegedUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takeinvite.php', [
            'email' => 'newbie@example.test',
            'body' => '<script>alert(1)</script>',
            'hash' => 'permanent',
        ]);

        $response->assertOk();
        $this->assertBarkContains(
            $response,
            'Please add a personal message.',
        );
    }

    public function test_missing_hash_barks_select_an_invite(): void
    {
        $user = $this->createPrivilegedUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takeinvite.php', [
            'email' => 'newbie@example.test',
            'body' => 'Hello!',
            // 'hash' deliberately omitted — `lang_takeinvite` keys
            // for the missing-hash bark exist only in zh; the
            // English fallback in the controller is "Please select
            // an invite to consume."
        ]);

        $response->assertOk();
        $this->assertBarkContains($response, 'Please select an invite');
    }

    public function test_email_already_in_use_by_another_user_barks(): void
    {
        $user = $this->createPrivilegedUser();
        $this->actingAs($user, 'nexus-web');

        $existingEmail = 'someone-else@example.test';
        $this->createLegacyUser(overrides: [
            'email' => $existingEmail,
            'lang' => self::ENGLISH_LANGUAGE_ID,
        ]);

        $response = $this->post('/takeinvite.php', [
            'email' => $existingEmail,
            'body' => 'Welcome!',
            'hash' => 'permanent',
        ]);

        $response->assertOk();
        $this->assertBarkContains($response, 'is already in use');
    }

    public function test_email_already_invited_barks(): void
    {
        $user = $this->createPrivilegedUser();
        $this->actingAs($user, 'nexus-web');

        $alreadyInvitedEmail = 'pending-invitee@example.test';
        // Seed an `invites` row with the same invitee email but a
        // different inviter — the legacy `WHERE invitee=?` test
        // ignores the inviter on purpose (the email may only be
        // claimed once across the entire system).
        Invite::query()->insert([
            'inviter' => $this->createLegacyUser()->id,
            'invitee' => $alreadyInvitedEmail,
            'hash' => str_repeat('a', 32),
            'time_invited' => now()->toDateTimeString(),
            'valid' => Invite::VALID_YES,
        ]);

        $response = $this->post('/takeinvite.php', [
            'email' => $alreadyInvitedEmail,
            'body' => 'Welcome!',
            'hash' => 'permanent',
        ]);

        $response->assertOk();
        $this->assertBarkContains(
            $response,
            'has already received an invitation',
        );
    }

    public function test_unknown_specific_hash_is_rejected(): void
    {
        $user = $this->createPrivilegedUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takeinvite.php', [
            'email' => 'newbie@example.test',
            'body' => 'Welcome!',
            // Anything other than `permanent` is treated as a
            // pre-existing temporary-invite hash and looked up in
            // the `invites` table. This one will not exist.
            'hash' => str_repeat('z', 32),
        ]);

        $response->assertOk();
        $this->assertBarkContains($response, 'Invitation hash not found');
    }

    public function test_already_consumed_specific_hash_is_rejected(): void
    {
        $user = $this->createPrivilegedUser();
        $this->actingAs($user, 'nexus-web');

        $consumedHash = str_repeat('b', 32);
        Invite::query()->insert([
            'inviter' => (int) $user->id,
            'invitee' => 'already-redeemed@example.test',
            'hash' => $consumedHash,
            'time_invited' => now()->toDateTimeString(),
            'valid' => Invite::VALID_YES,
        ]);

        $response = $this->post('/takeinvite.php', [
            'email' => 'somebody-new@example.test',
            'body' => 'Welcome!',
            'hash' => $consumedHash,
        ]);

        $response->assertOk();
        $this->assertBarkContains($response, 'is already in use');
    }

    /**
     * Build a CLASS_STAFF_LEADER user with a non-zero invite quota.
     * `user_can()` short-circuits to `true` for staff-leader, so
     * `UserRepository::getInviteBtnText()` returns successfully and
     * we fall through into the field-validation chain we want to
     * pin.
     *
     * @param  array<string,mixed>  $overrides
     */
    private function createPrivilegedUser(array $overrides = []): User
    {
        $user = $this->createLegacyUser(overrides: array_merge([
            'class' => User::CLASS_STAFF_LEADER,
            'lang' => self::ENGLISH_LANGUAGE_ID,
        ], $overrides));

        // `users.invites` is in `User::$commonFields` but not
        // `User::$fillable`, so `User::create([... 'invites' => N])`
        // silently drops it. Stamp it directly via the query
        // builder.
        NexusDB::table('users')
            ->where('id', $user->id)
            ->update(['invites' => 5]);
        $user->refresh();

        return $user;
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

    /**
     * Assert the response body contains the literal text plus the
     * legacy "Invitation failed" envelope. Phrased this way so the
     * tests are robust whether the legacy `stdhead()`/`stdmsg()`/
     * `stdfoot()` chrome is bootstrapped or the controller's
     * chrome-less fallback rendered instead.
     */
    private function assertBarkContains(\Illuminate\Testing\TestResponse $response, string $needle): void
    {
        $body = (string) $response->getContent();
        $this->assertStringContainsString(
            $needle,
            $body,
            "Expected bark response body to contain [$needle]; got:\n".substr($body, 0, 500),
        );
        $this->assertStringContainsString(
            'Invitation failed',
            $body,
            "Expected bark response body to contain the 'Invitation failed' heading; got:\n".substr($body, 0, 500),
        );
    }

    private function assertBarkResponse(\Illuminate\Testing\TestResponse $response): void
    {
        $body = (string) $response->getContent();
        $this->assertStringContainsString(
            'Invitation failed',
            $body,
            "Expected bark response body; got:\n".substr($body, 0, 500),
        );
    }

    /**
     * Mirrors the helper in `AttendanceControllerTest`. The settings
     * table is keyed on `name`; `Setting::query()->updateOrCreate`
     * either flips an existing row or seeds a new one for the
     * test.
     */
    private function setSetting(string $name, string $value): void
    {
        $now = Carbon::now()->toDateTimeString();
        Setting::query()->updateOrCreate(
            ['name' => $name],
            ['value' => $value, 'updated_at' => $now],
        );
        if (function_exists('clear_setting_cache')) {
            clear_setting_cache();
        }
    }
}
