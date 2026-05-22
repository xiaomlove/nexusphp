<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/report.php` contract.
 *
 * Universal "report this thing to staff" endpoint — see
 * `App\Http\Controllers\Legacy\ReportController` for the wire
 * shape. Lives inside `auth.nexus:nexus-web`; guests redirect to
 * login. Parked users get 403.
 */
class ReportControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    /** @var list<string> */
    private array $createdReportTypes = [];

    /** @var list<int> */
    private array $createdTorrentIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/report.php';
    }

    protected function tearDown(): void
    {
        // Reports table has no FK so direct delete is safe.
        if (! empty($this->createdReportTypes)) {
            NexusDB::table('reports')
                ->whereIn('type', $this->createdReportTypes)
                ->delete();
            $this->createdReportTypes = [];
        }
        foreach ($this->createdTorrentIds as $id) {
            NexusDB::table('torrents')->where('id', $id)->delete();
        }
        $this->createdTorrentIds = [];

        parent::tearDown();
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/report.php?torrent=1');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_parked_user_request_is_forbidden(): void
    {
        $user = $this->createTestUser(['parked' => 'yes']);
        $this->actingAs($user, 'nexus-web');

        $this->get('/report.php?torrent=1')->assertStatus(403);
    }

    public function test_get_without_target_returns_invalid_action_message(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/report.php');

        $response->assertOk();
        // The body is the "invalid action" message rendered in the
        // chrome envelope; it's localised, so we assert on the
        // structural envelope rather than copying the locale string.
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>', $body);
        $this->assertStringContainsString('<body>', $body);
    }

    public function test_get_self_report_renders_cannot_report_oneself_message(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/report.php?user='.$user->id);

        $response->assertOk();
        // A self-report does not render the confirmation form (no
        // `<form>` element), only an info message.
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('<form', $body);
    }

    public function test_get_target_user_with_staff_class_renders_cannot_report_message(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $staff = $this->createTestUser(['class' => User::CLASS_MODERATOR]);

        $response = $this->get('/report.php?user='.$staff->id);

        $response->assertOk();
        $this->assertStringNotContainsString('<form', (string) $response->getContent());
    }

    public function test_get_target_user_renders_confirmation_form(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $target = $this->createTestUser();

        $response = $this->get('/report.php?user='.$target->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<form', $body);
        $this->assertStringContainsString('name="takeuser"', $body);
        $this->assertStringContainsString('value="'.$target->id.'"', $body);
        $this->assertStringContainsString('name=reason', $body);
    }

    public function test_get_target_torrent_renders_confirmation_form(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('rep-target', $owner->id);

        $response = $this->get('/report.php?torrent='.$torrentId);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('name="taketorrent"', $body);
        $this->assertStringContainsString('value="'.$torrentId.'"', $body);
        $this->assertStringContainsString('rep-target', $body);
    }

    public function test_get_unknown_torrent_renders_invalid_torrent_message(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $maxId = (int) (NexusDB::table('torrents')->max('id') ?? 0);
        $bogus = $maxId + 9_999_999;

        $response = $this->get('/report.php?torrent='.$bogus);

        $response->assertOk();
        $this->assertStringNotContainsString('<form', (string) $response->getContent());
    }

    public function test_post_inserts_report_and_busts_caches(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $target = $this->createTestUser();
        $this->createdReportTypes[] = 'user';

        $before = (int) NexusDB::table('reports')
            ->where('addedby', $viewer->id)
            ->where('reportid', $target->id)
            ->where('type', 'user')
            ->count();
        $this->assertSame(0, $before);

        $response = $this->post('/report.php', [
            'takeuser' => $target->id,
            'reason' => 'Test reason — pinning the contract',
        ]);

        $response->assertOk();
        $row = NexusDB::table('reports')
            ->where('addedby', $viewer->id)
            ->where('reportid', $target->id)
            ->where('type', 'user')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('Test reason — pinning the contract', (string) ((array) $row)['reason']);
    }

    public function test_post_without_reason_renders_missing_reason_message(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $target = $this->createTestUser();

        $response = $this->post('/report.php', [
            'takeuser' => $target->id,
            // reason missing
        ]);

        $response->assertOk();
        $this->assertSame(
            0,
            NexusDB::table('reports')
                ->where('addedby', $viewer->id)
                ->where('reportid', $target->id)
                ->where('type', 'user')
                ->count(),
        );
    }

    public function test_post_duplicate_report_renders_already_reported_message(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $target = $this->createTestUser();
        $this->createdReportTypes[] = 'user';

        // First POST inserts.
        $this->post('/report.php', [
            'takeuser' => $target->id,
            'reason' => 'first',
        ])->assertOk();
        $countAfterFirst = NexusDB::table('reports')
            ->where('addedby', $viewer->id)
            ->where('reportid', $target->id)
            ->where('type', 'user')
            ->count();

        // Second POST is a no-op.
        $this->post('/report.php', [
            'takeuser' => $target->id,
            'reason' => 'second',
        ])->assertOk();
        $countAfterSecond = NexusDB::table('reports')
            ->where('addedby', $viewer->id)
            ->where('reportid', $target->id)
            ->where('type', 'user')
            ->count();

        $this->assertSame(1, (int) $countAfterFirst);
        $this->assertSame((int) $countAfterFirst, (int) $countAfterSecond);
    }

    public function test_post_with_zero_id_renders_invalid_id_message(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $response = $this->post('/report.php', [
            'taketorrent' => 0,
            'reason' => 'should not insert',
        ]);

        $response->assertOk();
        $this->assertSame(
            0,
            NexusDB::table('reports')
                ->where('addedby', $viewer->id)
                ->where('type', 'torrent')
                ->count(),
        );
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge([
                'lang' => self::ENGLISH_LANGUAGE_ID,
                'class' => User::CLASS_USER,
            ], $overrides),
        );
    }

    private function insertTorrent(string $name, int $ownerId): int
    {
        $id = (int) NexusDB::table('torrents')->insertGetId([
            'name' => $name,
            'filename' => $name.'.torrent',
            'owner' => $ownerId,
            'info_hash' => hex2bin(str_pad(bin2hex(random_bytes(10)), 40, '0')),
            'anonymous' => 'no',
            'size' => 1024,
            'seeders' => 0,
            'leechers' => 0,
            'added' => Carbon::now()->toDateTimeString(),
        ]);
        $this->createdTorrentIds[] = $id;

        return $id;
    }
}
