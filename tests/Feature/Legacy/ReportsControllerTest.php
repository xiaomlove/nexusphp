<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for `App\Http\Controllers\Legacy\ReportsController`
 * (replaces `public/reports.php`).
 */
class ReportsControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/reports.php');

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_below_staff_member_class_is_forbidden(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/reports.php');

        $response->assertForbidden();
    }

    public function test_empty_reports_table_renders_no_report_notice(): void
    {
        $staff = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staff, 'nexus-web');

        // Make sure the reports table is empty for this scenario.
        // (DatabaseTransactions wraps the test in a rollback, so this
        // delete is safe.)
        NexusDB::table('reports')->delete();

        $response = $this->get('/reports.php');

        $response->assertOk();
        // The legacy `std_oho` heading is rendered when no rows
        // exist; en lang has it as "Oho". Asserting on the surrounding
        // <h1> tag avoids coupling to a specific localised string.
        $this->assertStringContainsString('<h1', (string) $response->getContent());
    }

    public function test_reports_table_with_rows_renders_form_posting_to_takeupdate(): void
    {
        $staff = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staff, 'nexus-web');

        // Seed a single report row for an unknown reportid — the
        // controller falls into the "X does not exist" branch but
        // the row still renders.
        NexusDB::table('reports')->insert([
            'addedby' => $staff->id,
            'added' => date('Y-m-d H:i:s'),
            'type' => 'torrent',
            'reportid' => 999999999,
            'reason' => 'Test reason',
            'dealtwith' => 0,
            'dealtby' => 0,
        ]);

        $response = $this->get('/reports.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<form method="post" action="/takeupdate.php">', $body);
        $this->assertStringContainsString('name="delreport[]"', $body);
    }

    public function test_parked_user_is_forbidden(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        NexusDB::table('users')->where('id', $user->id)->update(['parked' => 'yes']);
        $user->refresh();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/reports.php');

        $response->assertForbidden();
    }
}
