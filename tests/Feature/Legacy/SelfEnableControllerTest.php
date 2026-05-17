<?php

namespace Tests\Feature\Legacy;

use App\Models\BonusLogs;
use App\Models\User;
use Database\Seeders\TestingDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class SelfEnableControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $defaultsLoaded = DB::table('settings')
            ->where('name', 'main.defaultlang')
            ->exists();
        if (! $defaultsLoaded) {
            (new TestingDataSeeder)->run();
        }

        $_SERVER['REQUEST_URI'] = '/self-enable.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/self-enable.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_parked_user_is_forbidden(): void
    {
        $user = $this->createTestUser(['enabled' => User::ENABLED_NO]);
        NexusDB::table('users')->where('id', $user->id)->update(['parked' => 'yes']);
        $user->refresh();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/self-enable.php');

        $response->assertForbidden();
    }

    public function test_feature_disabled_when_setting_is_zero(): void
    {
        $this->setSelfEnableBonus(0);

        $user = $this->createTestUser(['enabled' => User::ENABLED_NO]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/self-enable.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Feature not enabled', $body);
    }

    public function test_enabled_user_sees_normal_status_notice(): void
    {
        $this->setSelfEnableBonus(100);

        $user = $this->createTestUser(['enabled' => User::ENABLED_YES]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/self-enable.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString(
            'no self-service unblocking is required',
            $body,
        );
    }

    public function test_disabled_user_with_no_ban_log_sees_contact_admin_notice(): void
    {
        $this->setSelfEnableBonus(100);

        $user = $this->createTestUser(['enabled' => User::ENABLED_NO]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/self-enable.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString(
            'Your ban information cannot be found',
            $body,
        );
    }

    public function test_disabled_user_with_ban_log_and_enough_bonus_sees_form(): void
    {
        $this->setSelfEnableBonus(100);

        $user = $this->createTestUser([
            'enabled' => User::ENABLED_NO,
            'seedbonus' => 50000,
        ]);
        $this->actingAs($user, 'nexus-web');

        $banLogId = $this->insertBanLog([
            'uid' => $user->id,
            'username' => $user->username,
            'operator' => 'sysop',
            'reason' => 'cheating',
            'created_at' => Carbon::now()->subDays(3)->toDateTimeString(),
        ]);

        try {
            $response = $this->get('/self-enable.php');

            $response->assertOk();
            $body = (string) $response->getContent();

            $this->assertStringContainsString('Your latest ban information', $body);
            $this->assertStringContainsString('cheating', $body);
            $this->assertStringContainsString((string) $user->id, $body);
            $this->assertStringContainsString('<form method="post" action="self-enable.php">', $body);
            $this->assertStringContainsString('name="submit"', $body);
            $this->assertStringContainsString('Click to unblock', $body);
        } finally {
            NexusDB::table('user_ban_logs')->where('id', $banLogId)->delete();
        }
    }

    public function test_disabled_user_with_ban_log_and_insufficient_bonus_sees_warning(): void
    {
        $this->setSelfEnableBonus(100);

        $user = $this->createTestUser([
            'enabled' => User::ENABLED_NO,
            'seedbonus' => 10,
        ]);
        $this->actingAs($user, 'nexus-web');

        $banLogId = $this->insertBanLog([
            'uid' => $user->id,
            'username' => $user->username,
            'operator' => 'sysop',
            'reason' => 'cheating',
            'created_at' => Carbon::now()->subDays(3)->toDateTimeString(),
        ]);

        try {
            $response = $this->get('/self-enable.php');

            $response->assertOk();
            $body = (string) $response->getContent();

            $this->assertStringContainsString(
                'self-service unblocking is not possible',
                $body,
            );
            $this->assertStringNotContainsString('name="submit"', $body);
        } finally {
            NexusDB::table('user_ban_logs')->where('id', $banLogId)->delete();
        }
    }

    public function test_post_with_enough_bonus_enables_account_and_redirects(): void
    {
        $this->setSelfEnableBonus(100);

        $user = $this->createTestUser([
            'enabled' => User::ENABLED_NO,
            'seedbonus' => 50000,
            'class' => User::CLASS_USER,
        ]);
        $this->actingAs($user, 'nexus-web');

        $banLogId = $this->insertBanLog([
            'uid' => $user->id,
            'username' => $user->username,
            'operator' => 'sysop',
            'reason' => 'leech-warn',
            'created_at' => Carbon::now()->subDays(2)->toDateTimeString(),
        ]);

        try {
            $beforeBonus = (float) NexusDB::table('users')->where('id', $user->id)->value('seedbonus');

            $response = $this->post('/self-enable.php', ['submit' => '1']);

            $response->assertRedirect('/index.php');

            $row = (array) NexusDB::table('users')
                ->where('id', $user->id)
                ->select(['enabled', 'seedbonus'])
                ->first();

            $this->assertSame('yes', $row['enabled']);
            $this->assertLessThan($beforeBonus, (float) $row['seedbonus']);

            $bonusLogExists = DB::table('bonus_logs')
                ->where('uid', $user->id)
                ->where('business_type', BonusLogs::BUSINESS_TYPE_SELF_ENABLE)
                ->exists();
            $this->assertTrue($bonusLogExists, 'expected bonus_logs row for self-enable');
        } finally {
            NexusDB::table('user_ban_logs')->where('id', $banLogId)->delete();
            DB::table('bonus_logs')->where('uid', $user->id)->delete();
        }
    }

    public function test_post_with_insufficient_bonus_does_not_enable_account(): void
    {
        $this->setSelfEnableBonus(100);

        $user = $this->createTestUser([
            'enabled' => User::ENABLED_NO,
            'seedbonus' => 10,
        ]);
        $this->actingAs($user, 'nexus-web');

        $banLogId = $this->insertBanLog([
            'uid' => $user->id,
            'username' => $user->username,
            'operator' => 'sysop',
            'reason' => 'cheating',
            'created_at' => Carbon::now()->subDays(3)->toDateTimeString(),
        ]);

        try {
            $response = $this->post('/self-enable.php', ['submit' => '1']);

            $response->assertOk();
            $this->assertStringContainsString(
                'self-service unblocking is not possible',
                (string) $response->getContent(),
            );

            $stillDisabled = NexusDB::table('users')->where('id', $user->id)->value('enabled');
            $this->assertSame('no', $stillDisabled);
        } finally {
            NexusDB::table('user_ban_logs')->where('id', $banLogId)->delete();
        }
    }

    public function test_post_is_csrf_exempt(): void
    {
        $this->setSelfEnableBonus(100);

        $user = $this->createTestUser([
            'enabled' => User::ENABLED_NO,
            'seedbonus' => 10,
        ]);
        $this->actingAs($user, 'nexus-web');

        $banLogId = $this->insertBanLog([
            'uid' => $user->id,
            'username' => $user->username,
            'operator' => 'sysop',
            'reason' => 'cheating',
            'created_at' => Carbon::now()->subDays(3)->toDateTimeString(),
        ]);

        try {
            $response = $this->post('/self-enable.php', ['submit' => '1']);
            $response->assertOk();
            $this->assertStringNotContainsString('CSRF', (string) $response->getContent());
        } finally {
            NexusDB::table('user_ban_logs')->where('id', $banLogId)->delete();
        }
    }

    public function test_ban_log_reason_is_html_escaped(): void
    {
        $this->setSelfEnableBonus(100);

        $user = $this->createTestUser(['enabled' => User::ENABLED_NO]);
        $this->actingAs($user, 'nexus-web');

        $banLogId = $this->insertBanLog([
            'uid' => $user->id,
            'username' => $user->username,
            'operator' => 'sysop',
            'reason' => '<script>alert(1)</script>',
            'created_at' => Carbon::now()->subDays(1)->toDateTimeString(),
        ]);

        try {
            $response = $this->get('/self-enable.php');

            $response->assertOk();
            $body = (string) $response->getContent();

            $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        } finally {
            NexusDB::table('user_ban_logs')->where('id', $banLogId)->delete();
        }
    }

    private function setSelfEnableBonus(int $value): void
    {
        $now = Carbon::now()->toDateTimeString();
        NexusDB::table('settings')->updateOrInsert(
            ['name' => 'bonus.self_enable'],
            [
                'value' => (string) $value,
                'autoload' => 'yes',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    /**
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
