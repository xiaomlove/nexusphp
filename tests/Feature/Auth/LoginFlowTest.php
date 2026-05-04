<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\LegacyHttpFeatureTestCase;

/**
 * Feature tests for the legacy `public/takelogin.php` flow.
 *
 * Strategy:
 *   - We disable challenge-response (`security.use_challenge_response_authentication = no`)
 *     and captcha (`security.iv = no`) at the start of every test so we can
 *     POST plain `username` + `password`. Re-enabling them is out of scope —
 *     they're settings-flag behaviour, not login flow correctness.
 *   - Each test seeds its own user with a known md5(`secret . password . secret`)
 *     so the `!auth_key` branch in takelogin.php matches.
 */
class LoginFlowTest extends LegacyHttpFeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const PASSWORD = 'p4ssw0rd-test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->disableChallengeResponseAndCaptcha();
    }

    public function test_valid_credentials_set_login_cookie_and_redirect_to_index(): void
    {
        $user = $this->createLegacyUser(self::PASSWORD);

        $response = $this->http->post('takelogin.php', [
            'form_params' => [
                'username' => $user->username,
                'password' => self::PASSWORD,
            ],
        ]);

        $this->assertSame(
            302,
            $response->getStatusCode(),
            'Expected 302 redirect on valid login. '.$this->serverLogTail()
        );
        $this->assertStringContainsString('index.php', $response->getHeaderLine('Location'));

        $this->assertCookieSet('c_secure_pass');

        $user->refresh();
        $this->assertNotEmpty(
            $user->auth_key,
            '`auth_key` should be populated on first successful login.'
        );
        $this->assertNotNull(
            $user->last_login,
            '`last_login` should be updated on successful login.'
        );
    }

    public function test_invalid_password_does_not_set_login_cookie(): void
    {
        $user = $this->createLegacyUser(self::PASSWORD);

        $response = $this->http->post('takelogin.php', [
            'form_params' => [
                'username' => $user->username,
                'password' => 'definitely-wrong',
            ],
        ]);

        // Legacy stderr() emits 200 OK with an HTML error page rather than
        // a 401/403, so we can't rely on status code. Instead, assert no
        // login cookie was set and an attempt row was recorded.
        $this->assertCookieNotSet('c_secure_pass', 'No login cookie should be set when the password is wrong.');
        $attempts = (int) DB::table('loginattempts')->count();
        $this->assertGreaterThan(
            0,
            $attempts,
            'A `loginattempts` row should be inserted for a failed login.'
        );

        $user->refresh();
        $this->assertEmpty(
            $user->auth_key,
            '`auth_key` should not be populated on failed login.'
        );
    }

    public function test_unknown_username_returns_failed_login_page(): void
    {
        $response = $this->http->post('takelogin.php', [
            'form_params' => [
                'username' => 'nosuch_'.bin2hex(random_bytes(4)),
                'password' => self::PASSWORD,
            ],
        ]);

        // Failed login pages are HTML-rendered with stderr() — confirm we
        // didn't accidentally land on a redirect (i.e. we didn't get logged in).
        $this->assertNotSame(
            302,
            $response->getStatusCode(),
            'Unknown user must not redirect to index.php.'.$this->serverLogTail()
        );
        $this->assertCookieNotSet('c_secure_pass');
    }

    public function test_pending_account_blocks_login(): void
    {
        $user = $this->createPendingUser(self::PASSWORD);

        $response = $this->http->post('takelogin.php', [
            'form_params' => [
                'username' => $user->username,
                'password' => self::PASSWORD,
            ],
        ]);

        $this->assertNotSame(
            302,
            $response->getStatusCode(),
            'Pending accounts must not be redirected to index.php after login attempt.'
        );
        $this->assertCookieNotSet('c_secure_pass', 'Pending accounts must not receive `c_secure_pass`.');
        $body = strtolower((string) $response->getBody());
        $this->assertTrue(
            str_contains($body, 'verified') || str_contains($body, 'confirm'),
            'Response body should explain the account has not been verified/confirmed.'
        );
    }

    public function test_missing_password_field_does_not_authenticate(): void
    {
        $user = $this->createLegacyUser(self::PASSWORD);

        $response = $this->http->post('takelogin.php', [
            'form_params' => [
                'username' => $user->username,
                // no `password`
            ],
        ]);

        $this->assertNotSame(302, $response->getStatusCode());
        $this->assertCookieNotSet('c_secure_pass', 'No password means no login cookie should be issued.');
    }

    private function disableChallengeResponseAndCaptcha(): void
    {
        DB::table('settings')->upsert(
            [
                [
                    'name' => 'security.use_challenge_response_authentication',
                    'value' => 'no',
                    'autoload' => 'yes',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'security.iv',
                    'value' => 'no',
                    'autoload' => 'yes',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            ['name'],
            ['value', 'updated_at']
        );

        // Wipe the legacy Redis settings cache so the built-in server picks up
        // the freshly written values on the next request.
        $this->forgetLegacySettingsCache();
    }

    private function forgetLegacySettingsCache(): void
    {
        try {
            $redis = app('redis')->connection();
            $redis->del('nexus_settings_in_laravel');
            $redis->del('all_settings');
        } catch (\Throwable) {
            // No Redis available locally; legacy cache will fall back to DB.
        }
    }
}
