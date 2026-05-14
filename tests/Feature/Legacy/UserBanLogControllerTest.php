<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/user-ban-log.php` contract.
 *
 * Administrator+ only listing of `user_ban_logs` entries. Guests get
 * a login redirect; users below `User::CLASS_ADMINISTRATOR` get a 403
 * — the legacy script had no auth check at all, which is the same
 * anti-pattern the rest of Phase 2 replaces with a real 403 (see
 * `DonorlistControllerTest` for the canonical example).
 */
class UserBanLogControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/user-ban-log.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/user-ban-log.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_moderator_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/user-ban-log.php')->assertForbidden();
    }

    public function test_administrator_get_renders_ban_log_table(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $logId = $this->insertBanLog([
            'uid' => 12345,
            'username' => 'banned_user_'.bin2hex(random_bytes(3)),
            'operator' => 'mod1',
            'reason' => 'cheat',
        ]);

        try {
            $response = $this->get('/user-ban-log.php');

            $response->assertOk();
            $body = (string) $response->getContent();

            $this->assertStringContainsString('<title>User ban log</title>', $body);
            $this->assertStringContainsString('User ban log', $body);
            $this->assertStringContainsString('12345', $body);
            $this->assertStringContainsString('cheat', $body);
            $this->assertStringContainsString('name="q"', $body);
        } finally {
            NexusDB::table('user_ban_logs')->where('id', $logId)->delete();
        }
    }

    public function test_filter_q_narrows_results_to_matching_username(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $aliceId = $this->insertBanLog([
            'uid' => 1001,
            'username' => 'alice_'.bin2hex(random_bytes(3)),
            'operator' => 'mod',
            'reason' => 'alice-reason',
        ]);
        $bobId = $this->insertBanLog([
            'uid' => 1002,
            'username' => 'bob_'.bin2hex(random_bytes(3)),
            'operator' => 'mod',
            'reason' => 'bob-reason',
        ]);

        try {
            $response = $this->get('/user-ban-log.php?q=alice');

            $response->assertOk();
            $body = (string) $response->getContent();
            $this->assertStringContainsString('alice-reason', $body);
            $this->assertStringNotContainsString('bob-reason', $body);
        } finally {
            NexusDB::table('user_ban_logs')->whereIn('id', [$aliceId, $bobId])->delete();
        }
    }

    public function test_html_in_reason_is_escaped(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $logId = $this->insertBanLog([
            'uid' => 1003,
            'username' => 'xss_'.bin2hex(random_bytes(3)),
            'operator' => 'mod',
            'reason' => '<script>alert(1)</script>',
        ]);

        try {
            $response = $this->get('/user-ban-log.php');

            $body = (string) $response->getContent();
            $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        } finally {
            NexusDB::table('user_ban_logs')->where('id', $logId)->delete();
        }
    }

    /**
     * Insert a row into `user_ban_logs`. Returns the new `id`. We use
     * the query builder directly rather than `UserBanLog::create()`
     * because the model has `$timestamps = true` and that adds CI
     * brittleness around `updated_at` precision in MySQL 5.7.
     *
     * @param  array<string,mixed>  $row
     */
    private function insertBanLog(array $row): int
    {
        $now = Carbon::now()->toDateTimeString();

        return (int) NexusDB::table('user_ban_logs')->insertGetId(array_merge([
            'created_at' => $now,
            'updated_at' => $now,
        ], $row));
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
