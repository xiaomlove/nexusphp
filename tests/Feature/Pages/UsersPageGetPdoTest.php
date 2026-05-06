<?php

namespace Tests\Feature\Pages;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\LegacyHttpFeatureTestCase;

/**
 * End-to-end regression test for the `NexusDB::getPdo()` call-site in
 * `public/users.php`.
 *
 * `public/users.php` line 21:
 *
 *     $query = "username LIKE " . \Nexus\Database\NexusDB::getPdo()->quote("%$search%") . " AND status='confirmed'";
 *
 * Before PR #88, hitting `/users.php?search=foo` (with a logged-in user
 * that has the `viewuserlist` capability) would have triggered a fatal
 * `Call to undefined method Nexus\Database\NexusDB::getPdo()` error.
 *
 * This test:
 *   - Logs in as a high-class user (CLASS_STAFF_LEADER) so the
 *     `user_can('viewuserlist', true)` gate passes regardless of the
 *     site's `$AUTHORITY['viewuserlist']` setting.
 *   - Hits `/users.php?search=<random-string>` so the search branch on
 *     line 19-24 (the one that calls `NexusDB::getPdo()->quote(...)`)
 *     is exercised.
 *   - Asserts the response is a normal HTTP 200 (i.e. no fatal error
 *     surfaced via PHP's built-in server).
 *   - Asserts the response body does NOT contain the
 *     "Call to undefined method" string that PHP would emit on a
 *     fatal-error trace.
 */
class UsersPageGetPdoTest extends LegacyHttpFeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const PASSWORD = 'p4ssw0rd-test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->disableChallengeResponseAndCaptcha();
    }

    public function test_users_page_search_does_not_trigger_undefined_method_fatal(): void
    {
        $user = $this->createLegacyUser(self::PASSWORD, ['class' => User::CLASS_STAFF_LEADER]);
        $this->loginAs($user, self::PASSWORD);

        // Random-ish search term so we can't accidentally pull a real user
        // page that happens to have search-resilient HTML.
        $search = 'tu_'.bin2hex(random_bytes(4));

        $response = $this->http->get('users.php', [
            'query' => ['search' => $search],
        ]);

        $this->assertSame(
            200,
            $response->getStatusCode(),
            '/users.php?search=... should render normally for a logged-in '
            .'high-class user. '.$this->serverLogTail()
        );

        $body = (string) $response->getBody();
        $this->assertStringNotContainsString(
            'Call to undefined method',
            $body,
            'Response body should not contain a "Call to undefined method" error '
            .'(this would indicate NexusDB::getPdo() is missing — see PR #88).'
        );
        $this->assertStringNotContainsString(
            'Nexus\\Database\\NexusDB::getPdo',
            $body,
            'Response body should not surface the NexusDB::getPdo signature in '
            .'an error trace.'
        );
    }

    /**
     * Replicates the cookie-jar login flow used in tests/Feature/Upload/UploadFlowTest.php
     * so the test cookie jar holds a `c_secure_pass` value that legacy
     * `loggedinorreturn()` accepts.
     */
    private function loginAs(User $user, string $password): void
    {
        $response = $this->http->post('takelogin.php', [
            'form_params' => [
                'username' => $user->username,
                'password' => $password,
            ],
        ]);
        $this->assertSame(
            302,
            $response->getStatusCode(),
            'Pre-test login must succeed before the users.php test runs. '
            .$this->serverLogTail()
        );
        $this->assertCookieSet('c_secure_pass');
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

        try {
            $redis = app('redis')->connection();
            $redis->del('nexus_settings_in_laravel');
            $redis->del('all_settings');
        } catch (\Throwable) {
            // No Redis available; legacy cache will fall back to DB.
        }
    }
}
