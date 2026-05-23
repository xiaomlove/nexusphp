<?php

namespace Tests\Feature\Legacy;

use App\Models\Invite;
use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `GET /signup.php` contract introduced by the
 * Phase 2 migration of `public/signup.php` (auth-flow batch part 2
 * of 3 — see `SignupController` PHPDoc).
 *
 * Conservative pin: focus on the auth gates (already-logged-in
 * redirect, IP-ban abort, invalid-invite 404), the form's wire
 * shape (action + input names), and the invite-code lookup. The
 * actual user-create happy path lives on `/takesignup.php` and is
 * pinned by `TakeSignupControllerTest`.
 */
class SignupControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/signup.php';
    }

    public function test_already_logged_in_user_is_redirected_to_index(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/signup.php');

        $response->assertRedirect('/index.php');
    }

    public function test_guest_sees_form_with_takesignup_action_and_required_input_names(): void
    {
        $response = $this->get('/signup.php');

        $response->assertOk();
        $body = (string) $response->getContent();

        // Form action MUST stay `takesignup.php` — the migration
        // contract is "URL preserved exactly" so the
        // `TakeSignupController` write-handler keeps receiving the
        // same payloads without a JS / template edit.
        $this->assertStringContainsString(
            'action="takesignup.php"',
            $body,
            'Signup form must POST to /takesignup.php',
        );

        // Form id is referenced by `render_password_hash_js`
        // — flipping it would break the JS-side wiring.
        $this->assertStringContainsString('id="signup-form"', $body);

        // Required input names that `TakeSignupController` and the
        // existing front-end both depend on.
        $this->assertStringContainsString('name="wantusername"', $body);
        $this->assertStringContainsString('class="wantpassword"', $body);
        $this->assertStringContainsString('class="passagain"', $body);
        $this->assertStringContainsString('name="email"', $body);
        $this->assertStringContainsString('name="country"', $body);
        $this->assertStringContainsString('name="gender"', $body);
        $this->assertStringContainsString('name="rulesverify"', $body);
        $this->assertStringContainsString('name="faqverify"', $body);
        $this->assertStringContainsString('name="ageverify"', $body);
        $this->assertStringContainsString('name="hash"', $body);
    }

    public function test_invite_mode_with_unknown_hash_returns_404(): void
    {
        $response = $this->get('/signup.php?type=invite&invitenumber=does-not-exist-'.bin2hex(random_bytes(8)));

        $response->assertNotFound();
    }

    public function test_invite_mode_with_missing_hash_returns_400(): void
    {
        $response = $this->get('/signup.php?type=invite');

        // Legacy script: `stderr($lang_signup['std_error'], "Require
        // invitenumber")` — HTTP 200 envelope. We tighten this to
        // a 400 because the client supplied an incomplete URL
        // and there's no recoverable input.
        $response->assertStatus(400);
    }

    public function test_invite_mode_with_valid_hash_renders_invite_form(): void
    {
        // Create an inviter + invite row with a known hash. The
        // controller looks the hash up and stamps the inviter id
        // into the hidden `inviter` input.
        $inviter = $this->createTestUser();

        $hash = bin2hex(random_bytes(16));
        NexusDB::insert('invites', [
            'inviter' => (int) $inviter->id,
            'hash' => $hash,
            'valid' => Invite::VALID_YES,
            'time_invited' => now()->toDateTimeString(),
        ]);

        $response = $this->get('/signup.php?type=invite&invitenumber='.$hash);

        $response->assertOk();
        $body = (string) $response->getContent();

        // The inviter id must be stamped into a hidden input so
        // `TakeSignupController` can validate it on the POST side.
        $this->assertStringContainsString(
            'name="inviter" value="'.$inviter->id.'"',
            $body,
            'Inviter id should round-trip through a hidden input',
        );

        // `type=invite` hidden input should also travel with the
        // POST so the write-handler knows which mode to use.
        $this->assertStringContainsString('name="type" value="invite"', $body);

        // The hash should be stamped into the `hash` hidden input.
        $this->assertStringContainsString('value="'.$hash.'"', $body);
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

            $response = $this->get('/signup.php');

            $response->assertForbidden();
        } finally {
            unset($GLOBALS['maxloginattempts']);
        }
    }

    public function test_invite_hash_is_html_escaped_in_form(): void
    {
        // Even though `invites.hash` is a generated nonce, defence
        // in depth — ensure the controller HTML-escapes it before
        // stamping into the `value="..."` attribute.
        $inviter = $this->createTestUser();
        $hash = 'a"><script>alert(1)</script>';
        NexusDB::insert('invites', [
            'inviter' => (int) $inviter->id,
            'hash' => $hash,
            'valid' => Invite::VALID_YES,
            'time_invited' => now()->toDateTimeString(),
        ]);

        $response = $this->get('/signup.php?type=invite&invitenumber='.urlencode($hash));

        $response->assertOk();
        $body = (string) $response->getContent();

        $this->assertStringNotContainsString(
            '<script>alert(1)</script>',
            $body,
            'Invite hash must be HTML-escaped before rendering',
        );
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
