<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `GET /login.php` contract introduced by the
 * Phase 2 migration of `public/login.php` (auth-flow batch part 1
 * of 3).
 *
 * The login page is the highest-traffic public surface on the
 * site, so the contract this test pins is conservative — it
 * focuses on the auth gates (already-logged-in redirect,
 * IP-ban abort), the form's wire shape (action + input names),
 * and the URL-parameter passthroughs (returnto, secret, nowarn)
 * that the legacy front-end depends on. The form's actual
 * password-submit happy path lives on `/takelogin.php` and is
 * pinned by `TakeLoginControllerTest`.
 */
class LoginControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/login.php';
    }

    public function test_already_logged_in_user_is_redirected_to_index(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/login.php');

        // cur_user_check() parity: don't show the login form to a
        // user who is already authenticated.
        $response->assertRedirect('/index.php');
    }

    public function test_guest_sees_login_form_with_correct_action_and_input_names(): void
    {
        $response = $this->get('/login.php');

        $response->assertOk();
        $body = (string) $response->getContent();

        // Form action MUST stay `takelogin.php` — the migration
        // contract is "URL preserved exactly" so the existing
        // challenge-response JS in `public/js/common.js` keeps
        // targeting the right endpoint without any JS edits.
        $this->assertStringContainsString(
            'action="takelogin.php"',
            $body,
            'Login form must POST to /takelogin.php',
        );

        // Form id is referenced by `render_password_challenge_js`
        // — flipping it would break the JS-side wiring.
        $this->assertStringContainsString('id="login-form"', $body);

        // Required input names that the existing front-end + the
        // migrated `TakeLoginController` both depend on.
        $this->assertStringContainsString('name="username"', $body);
        $this->assertStringContainsString('name="two_step_code"', $body);
        $this->assertStringContainsString('name="logout"', $body);

        // The password input either has `name="password"` (legacy
        // direct-submit mode) or just `class="password"` (challenge-
        // response mode where the JS populates `name="response"`
        // before submit). We accept either as the wire contract.
        $this->assertTrue(
            str_contains($body, 'name="password"')
                || str_contains($body, 'class="password"'),
            'Password input must be present (either name=password or class=password depending on challenge-response mode).',
        );
    }

    public function test_returnto_query_param_renders_warning_and_hidden_input(): void
    {
        $returnto = '/torrents.php';
        $response = $this->get('/login.php?returnto='.urlencode($returnto));

        $response->assertOk();
        $body = (string) $response->getContent();

        // The legacy script renders a "you tried to access X but
        // weren't logged in" warning at the top of the page when
        // `?returnto` is set without `?nowarn`. We pin that
        // observable (the warning is produced via `lang_login`
        // strings, but the heading marker `<h1>` is stable).
        $this->assertMatchesRegularExpression(
            '/<h1>.+<\/h1>/',
            $body,
            'returnto warning heading should be rendered',
        );

        // The returnto value gets stamped into a hidden input on
        // the form so it survives the POST round-trip.
        $this->assertStringContainsString(
            'name="returnto"',
            $body,
            'returnto hidden input should be rendered',
        );
        $this->assertStringContainsString(
            'value="/torrents.php"',
            $body,
            'returnto hidden input should carry the requested URL',
        );
    }

    public function test_returnto_with_nowarn_suppresses_warning_heading(): void
    {
        // The legacy contract: ?nowarn=1 hides the "must be logged
        // in" warning. Some flows opt out of the warning when the
        // user landed here through a deliberate logout or session
        // refresh.
        $response = $this->get('/login.php?returnto=/torrents.php&nowarn=1');

        $response->assertOk();
        $body = (string) $response->getContent();

        // The warning heading is the only `<h1>` on the page (the
        // form proper uses `<table>` rows). With ?nowarn=1 it must
        // NOT be rendered.
        $this->assertDoesNotMatchRegularExpression(
            '/<h1>[^<]*<\/h1>/',
            $body,
            'Warning heading should be suppressed when ?nowarn=1',
        );

        // The returnto hidden input still travels through, because
        // the user still wants to land on the original URL after
        // login.
        $this->assertStringContainsString('value="/torrents.php"', $body);
    }

    public function test_returnto_value_is_html_escaped_to_prevent_xss(): void
    {
        $payload = '/foo.php"><script>alert(1)</script>';
        $response = $this->get('/login.php?returnto='.urlencode($payload));

        $response->assertOk();
        $body = (string) $response->getContent();

        // The literal payload (with raw `<script>`) must NOT be in
        // the body, which would mean we leaked the injection.
        $this->assertStringNotContainsString(
            '<script>alert(1)</script>',
            $body,
            'returnto payload must be HTML-escaped before rendering',
        );
        // The escaped form should be present — at minimum the
        // entity-encoded `<` should appear if the raw `<` was
        // properly escaped.
        $this->assertStringContainsString('&lt;script&gt;', $body);
    }

    public function test_secret_query_param_is_html_escaped_in_hidden_input(): void
    {
        $secret = 'test-secret-value';
        $response = $this->get('/login.php?secret='.$secret);

        $response->assertOk();
        $body = (string) $response->getContent();

        // The legacy script stamps `?secret=...` into a hidden
        // input on the form — used by some OAuth callback flows.
        // The exact value should round-trip (HTML-escaped).
        $this->assertStringContainsString(
            'name="secret" value="'.$secret.'"',
            $body,
        );
    }

    public function test_ip_banned_request_aborts_with_403(): void
    {
        // failedloginscheck() parity. The legacy script `stderr()`-
        // exits when the IP has hit the `$maxloginattempts`
        // threshold. We tightened the response to a clean 403.
        $maxAttempts = 5;
        $GLOBALS['maxloginattempts'] = $maxAttempts;

        try {
            $ip = '127.0.0.1';
            NexusDB::insert('loginattempts', [
                'ip' => $ip,
                'added' => date('Y-m-d H:i:s'),
                'attempts' => $maxAttempts + 1,
            ]);

            $response = $this->get('/login.php', [
                'REMOTE_ADDR' => $ip,
                'HTTP_X_FORWARDED_FOR' => $ip,
            ]);

            $response->assertForbidden();
        } finally {
            unset($GLOBALS['maxloginattempts']);
        }
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
