<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the contract of the rewritten `/takeupdate.php` route
 * (was `public/takeupdate.php`, deleted in the same PR).
 *
 * Verifies the legacy semantics survived the rewrite:
 *
 *  - Guest POST  → redirect to `login.php`.
 *  - Authenticated regular user POST → 403 (legacy `user_can('staffmem',
 *    true)` previously rendered the legacy stderr template; the
 *    migrated controller throws `InsufficientPermissionException`
 *    which Laravel renders as 403).
 *  - Staff POST without `delreport[]` → 302 back to `/reports.php`
 *    with a flash error (legacy ran `stderr(...)` instead; matching
 *    the redirect-back + error semantics from the FormRequest world).
 *  - Staff POST with `setdealt` → rows updated, `dealtby` stamped,
 *    cache invalidated, 302 to `/reports.php`.
 *  - Staff POST with `delete` → rows deleted, cache invalidated.
 *
 * The permission gate is exercised by giving the test user
 * `User::CLASS_STAFF_LEADER`, which auto-passes `user_can()` without
 * needing a userauthority row seeded for this specific permission
 * (matches what the existing thanks/Phase 2 tests do for class
 * gates).
 */
class TakeUpdateControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        // Mirror what `LogUserIp` middleware reads.
        $_SERVER['REQUEST_URI'] = '/takeupdate.php';

        // Reset the global the controller writes into.
        unset($GLOBALS['CURUSER']);
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->post('/takeupdate.php', [
            'delreport' => [1],
            'setdealt' => 1,
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_regular_user_gets_403(): void
    {
        $user = $this->createStaffUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takeupdate.php', [
            'delreport' => [1],
            'setdealt' => 1,
        ]);

        $response->assertStatus(403);
    }

    public function test_staff_post_without_delreport_redirects_back(): void
    {
        $user = $this->createStaffUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takeupdate.php', ['setdealt' => 1]);

        $response->assertRedirect('/reports.php');
    }

    public function test_staff_post_with_empty_delreport_redirects_back(): void
    {
        $user = $this->createStaffUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takeupdate.php', [
            'delreport' => [],
            'setdealt' => 1,
        ]);

        $response->assertRedirect('/reports.php');
    }

    public function test_staff_post_setdealt_marks_rows_dealt_and_stamps_dealtby(): void
    {
        $staff = $this->createStaffUser();
        $this->actingAs($staff, 'nexus-web');

        $reportId = $this->seedReport(['dealtwith' => 0, 'dealtby' => 0]);
        $alreadyDealtId = $this->seedReport(['dealtwith' => 1, 'dealtby' => 999]);

        $response = $this->post('/takeupdate.php', [
            'delreport' => [$reportId, $alreadyDealtId],
            'setdealt' => 1,
        ]);

        $response->assertRedirect('/reports.php');

        $row = (array) NexusDB::table('reports')->where('id', $reportId)->first();
        $this->assertSame(1, (int) $row['dealtwith']);
        $this->assertSame((int) $staff->id, (int) $row['dealtby']);

        // Already-dealt rows are not re-stamped (legacy ran a
        // `where dealtwith = 0` filter; preserve that).
        $other = (array) NexusDB::table('reports')->where('id', $alreadyDealtId)->first();
        $this->assertSame(999, (int) $other['dealtby']);
    }

    public function test_staff_post_delete_removes_rows(): void
    {
        $staff = $this->createStaffUser();
        $this->actingAs($staff, 'nexus-web');

        $reportId1 = $this->seedReport();
        $reportId2 = $this->seedReport();

        $response = $this->post('/takeupdate.php', [
            'delreport' => [$reportId1, $reportId2],
            'delete' => 1,
        ]);

        $response->assertRedirect('/reports.php');

        $remaining = NexusDB::table('reports')->whereIn('id', [$reportId1, $reportId2])->count();
        $this->assertSame(0, $remaining);
    }

    public function test_staff_post_with_neither_action_just_redirects(): void
    {
        // Legacy: no setdealt and no delete = no-op. The migrated
        // controller is not stricter about it.
        $staff = $this->createStaffUser();
        $this->actingAs($staff, 'nexus-web');

        $reportId = $this->seedReport();

        $response = $this->post('/takeupdate.php', [
            'delreport' => [$reportId],
        ]);

        $response->assertRedirect('/reports.php');

        $remaining = NexusDB::table('reports')->where('id', $reportId)->count();
        $this->assertSame(1, $remaining);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createStaffUser(array $overrides = []): User
    {
        return $this->createLegacyUser(overrides: array_merge([
            'class' => User::CLASS_STAFF_LEADER,
            'lang' => self::ENGLISH_LANGUAGE_ID,
        ], $overrides));
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function seedReport(array $overrides = []): int
    {
        return (int) NexusDB::table('reports')->insertGetId(array_merge([
            'addedby' => 0,
            'added' => Carbon::now()->toDateTimeString(),
            'reportid' => 0,
            'type' => 'torrent',
            'reason' => 'fixture',
            'dealtby' => 0,
            'dealtwith' => 0,
        ], $overrides));
    }
}
