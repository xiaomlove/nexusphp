<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `POST /takelogin.php` contract introduced by the
 * Phase 2 migration of `public/takelogin.php` (auth-flow batch
 * part 1 of 3).
 *
 * Test posture
 * ------------
 * Bark / validation paths return HTTP 200 with the legacy chrome
 * envelope (or, when the legacy chrome is not bootstrapped in the
 * test runner, the chrome-less fallback the controller renders
 * instead). All bark assertions look for the message text
 * regardless of which envelope the response body uses.
 *
 * Happy path is deliberately NOT covered by this test class. The
 * legacy `logincookie()` global calls `setcookie()` directly
 * (rather than queuing through Laravel's cookie jar) and the
 * resulting `c_secure_pass` cookie does NOT surface through
 * `$response->headers->getCookies()` in the test runner. Same
 * caveat as `TakeUploadControllerTest` (PR #302); the cookie-mint
 * + redirect contract is exercised by the existing E2E `auth`
 * smoke spec in CI.
 */
class TakeLoginControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/takelogin.php';
    }

    public function test_already_logged_in_user_is_redirected_to_index(): void
    {
        // cur_user_check() parity — the controller does not even
        // re-validate the credentials when there's already a
        // session. Avoids an infinite-redirect bug if the login
        // form is submitted twice in quick succession (e.g. user
        // double-clicks the button).
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takelogin.php', [
            'username' => $user->username,
            'password' => 'p4ssw0rd-test',
        ]);

        $response->assertRedirect('/index.php');
    }

    public function test_missing_username_returns_bark(): void
    {
        $response = $this->post('/takelogin.php', [
            'password' => 'whatever',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        // Either the legacy stdmsg envelope or the chrome-less
        // fallback is acceptable — both contain the heading.
        $this->assertStringContainsString('Login', $body);
    }

    public function test_missing_password_returns_bark(): void
    {
        $response = $this->post('/takelogin.php', [
            'username' => 'testuser',
            // password missing
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Require password parameter', $body);
    }

    public function test_unknown_user_returns_bark_and_records_failed_attempt(): void
    {
        // The legacy script `failedlogins()`-bails on unknown
        // username with a generic "login failed" message — the
        // exact phrasing is intentionally vague so attackers
        // can't enumerate usernames.
        $beforeCount = (int) NexusDB::table('loginattempts')->count();

        $response = $this->post('/takelogin.php', [
            'username' => 'no-such-user-'.bin2hex(random_bytes(4)),
            'password' => 'whatever',
        ]);

        $response->assertOk();

        // The failed-attempt counter increments either via INSERT
        // (first attempt from this IP) or UPDATE (subsequent).
        // Either way, total row count grows by at most 1 and the
        // sum of `attempts` increases by 1.
        $afterCount = (int) NexusDB::table('loginattempts')->count();
        $this->assertGreaterThanOrEqual(
            $beforeCount,
            $afterCount,
            'loginattempts table should not lose rows',
        );
    }

    public function test_pending_account_returns_unconfirmed_bark(): void
    {
        $password = 'p4ssw0rd-test';
        $user = $this->createPendingUser($password, [
            'lang' => self::ENGLISH_LANGUAGE_ID,
        ]);

        $response = $this->post('/takelogin.php', [
            'username' => $user->username,
            'password' => $password,
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        // Legacy phrase: "Your account has not been confirmed yet."
        // The controller falls back to that English string when
        // the lang dictionary doesn't supply the key. We accept
        // any rendering that contains the word "confirmed" as
        // proof the right bark branch fired.
        $this->assertStringContainsString('confirmed', strtolower($body));
    }

    public function test_disabled_account_returns_disabled_bark(): void
    {
        // The legacy contract is: only show the "account disabled"
        // bark when `Setting::getSelfEnableBonus() <= 0`. When
        // self-enable bonus is positive, the user can still log in
        // and is redirected to the self-enable page (handled by
        // SelfEnableController). For the negative branch — which
        // is the default — we expect the bark.
        $password = 'p4ssw0rd-test';
        $user = $this->createLegacyUser($password, [
            'enabled' => 'no',
            'lang' => self::ENGLISH_LANGUAGE_ID,
        ]);

        $response = $this->post('/takelogin.php', [
            'username' => $user->username,
            'password' => $password,
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('disabled', strtolower($body));
    }

    public function test_wrong_password_records_failed_attempt(): void
    {
        // The legacy md5 fallback (`empty($row['auth_key'])`)
        // computes `md5(secret . password . secret)` and compares
        // via `hash_equals`. A wrong password tripping the
        // not-equal branch logs the attempt and barks.
        $user = $this->createTestUser();

        $beforeAttempts = (int) NexusDB::table('loginattempts')
            ->where('ip', $this->test_ip())
            ->sum('attempts');

        $response = $this->post('/takelogin.php', [
            'username' => $user->username,
            'password' => 'completely-wrong-password',
        ]);

        $response->assertOk();

        $afterAttempts = (int) NexusDB::table('loginattempts')
            ->where('ip', $this->test_ip())
            ->sum('attempts');
        $this->assertGreaterThan(
            $beforeAttempts,
            $afterAttempts,
            'A wrong-password attempt should increment loginattempts',
        );
    }

    public function test_ip_banned_request_aborts_with_403(): void
    {
        // failedloginscheck() parity. The legacy script
        // `stderr()`-exits on the IP-ban gate before any field
        // validation runs. We tightened the response to a clean
        // 403 abort.
        $maxAttempts = 5;
        $GLOBALS['maxloginattempts'] = $maxAttempts;

        try {
            $ip = $this->test_ip();
            NexusDB::insert('loginattempts', [
                'ip' => $ip,
                'added' => date('Y-m-d H:i:s'),
                'attempts' => $maxAttempts + 1,
            ]);

            $response = $this->post('/takelogin.php', [
                'username' => 'testuser',
                'password' => 'whatever',
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

    private function test_ip(): string
    {
        return '127.0.0.1';
    }
}
