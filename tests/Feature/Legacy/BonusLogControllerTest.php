<?php

namespace Tests\Feature\Legacy;

use App\Models\BonusLogs;
use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the contract of the rewritten `/bonus-log.php` route
 * (was `public/bonus-log.php`, deleted in the same PR).
 *
 * Phase 3 of the legacy migration — the rewrite replaces the
 * procedural script with a `BonusLogController` + Blade view +
 * `BonusLogService` triplet. The wire-level contract this test
 * pins is:
 *
 *   - Guests redirect to `login.php` (the legacy script's
 *     `loggedinorreturn()` did the same).
 *   - `?uid=` defaults to the viewer's id; a different `uid` is
 *     accepted only when the viewer holds the `viewhistory`
 *     permission (mapped to `PermissionEnum::VIEW_USER_HISTORY`).
 *   - Invalid `uid` returns 422; missing user returns 404.
 *   - Invalid `category` / `business_type` returns 422.
 *   - The seeded `bonus_logs` row is rendered with its `comment`
 *     and `created_at` columns.
 *   - HTML in the `comment` column is escaped (Blade default),
 *     closing the latent XSS sink the legacy `echo $row->comment`
 *     exposed.
 *   - Pagination renders 50 rows per page; second page is reachable
 *     via `?page=1` (0-indexed).
 */
class BonusLogControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/bonus-log.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/bonus-log.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_user_can_view_own_log(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $logId = $this->insertBonusLog([
            'uid' => $user->id,
            'business_type' => BonusLogs::BUSINESS_TYPE_REWARD_TORRENT,
            'old_total_value' => 100,
            'value' => 50,
            'new_total_value' => 150,
            'comment' => 'self log entry',
        ]);

        try {
            $response = $this->get('/bonus-log.php');

            $response->assertOk();
            $body = (string) $response->getContent();
            $this->assertStringContainsString('Bonus log', $body);
            $this->assertStringContainsString($user->username, $body);
            $this->assertStringContainsString('self log entry', $body);
        } finally {
            NexusDB::table('bonus_logs')->where('id', $logId)->delete();
        }
    }

    public function test_user_without_viewhistory_cannot_view_other_user_log(): void
    {
        $viewer = $this->createTestUser(['class' => User::CLASS_USER]);
        $other = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $this->get('/bonus-log.php?uid='.$other->id)->assertForbidden();
    }

    public function test_moderator_can_view_other_user_log(): void
    {
        $viewer = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $other = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $logId = $this->insertBonusLog([
            'uid' => $other->id,
            'business_type' => BonusLogs::BUSINESS_TYPE_REWARD_TORRENT,
            'old_total_value' => 200,
            'value' => 25,
            'new_total_value' => 225,
            'comment' => 'other user log entry',
        ]);

        try {
            $response = $this->get('/bonus-log.php?uid='.$other->id);

            $response->assertOk();
            $body = (string) $response->getContent();
            $this->assertStringContainsString($other->username, $body);
            $this->assertStringContainsString('other user log entry', $body);
        } finally {
            NexusDB::table('bonus_logs')->where('id', $logId)->delete();
        }
    }

    public function test_unknown_uid_returns_404(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/bonus-log.php?uid=999999999')->assertNotFound();
    }

    public function test_invalid_uid_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/bonus-log.php?uid=-1')->assertStatus(422);
    }

    public function test_invalid_category_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/bonus-log.php?category=totally-not-a-category')
            ->assertStatus(422);
    }

    public function test_invalid_business_type_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/bonus-log.php?business_type=999999')->assertStatus(422);
    }

    public function test_filter_by_business_type_narrows_results(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $rewardId = $this->insertBonusLog([
            'uid' => $user->id,
            'business_type' => BonusLogs::BUSINESS_TYPE_REWARD_TORRENT,
            'old_total_value' => 100,
            'value' => 50,
            'new_total_value' => 150,
            'comment' => 'reward-only row',
        ]);
        $otherId = $this->insertBonusLog([
            'uid' => $user->id,
            'business_type' => BonusLogs::BUSINESS_TYPE_BUY_MEDAL,
            'old_total_value' => 150,
            'value' => 25,
            'new_total_value' => 125,
            'comment' => 'medal-only row',
        ]);

        try {
            $response = $this->get(
                '/bonus-log.php?business_type='
                .BonusLogs::BUSINESS_TYPE_REWARD_TORRENT,
            );

            $response->assertOk();
            $body = (string) $response->getContent();
            $this->assertStringContainsString('reward-only row', $body);
            $this->assertStringNotContainsString('medal-only row', $body);
        } finally {
            NexusDB::table('bonus_logs')->whereIn('id', [$rewardId, $otherId])->delete();
        }
    }

    public function test_html_in_comment_is_escaped(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $logId = $this->insertBonusLog([
            'uid' => $user->id,
            'business_type' => BonusLogs::BUSINESS_TYPE_REWARD_TORRENT,
            'old_total_value' => 0,
            'value' => 10,
            'new_total_value' => 10,
            'comment' => '<script>alert(1)</script>',
        ]);

        try {
            $response = $this->get('/bonus-log.php');

            $body = (string) $response->getContent();
            $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        } finally {
            NexusDB::table('bonus_logs')->where('id', $logId)->delete();
        }
    }

    public function test_pagination_links_appear_when_total_exceeds_page_size(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $ids = [];
        for ($i = 0; $i < 51; $i++) {
            $ids[] = $this->insertBonusLog([
                'uid' => $user->id,
                'business_type' => BonusLogs::BUSINESS_TYPE_REWARD_TORRENT,
                'old_total_value' => $i,
                'value' => 1,
                'new_total_value' => $i + 1,
                'comment' => 'row '.$i,
            ]);
        }

        try {
            $response = $this->get('/bonus-log.php');

            $response->assertOk();
            $body = (string) $response->getContent();
            $this->assertStringContainsString('page=1', $body);
        } finally {
            NexusDB::table('bonus_logs')->whereIn('id', $ids)->delete();
        }
    }

    public function test_empty_log_renders_nothing_found_row(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        NexusDB::table('bonus_logs')->where('uid', $user->id)->delete();

        $response = $this->get('/bonus-log.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Nothing found.', $body);
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function insertBonusLog(array $row): int
    {
        $now = Carbon::now()->toDateTimeString();

        return (int) NexusDB::table('bonus_logs')->insertGetId(array_merge([
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
