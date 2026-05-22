<?php

namespace Tests\Feature\Legacy;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for the Phase 2 `DeleteTorrentController` (replaces
 * the legacy `public/delete.php` that PR #285 deleted).
 *
 * The controller is a POST-only handler invoked by the legacy
 * `<form method="post" action="delete.php">` block in
 * `public/edit.php:254-271`. PR #285 shipped the migration without
 * any test coverage; this PR backfills the contract.
 */
class DeleteTorrentControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    /** @var array<int,string> */
    private array $createdTorrentFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/delete.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->createdTorrentFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->createdTorrentFiles = [];

        parent::tearDown();
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->post('/delete.php', ['id' => 1, 'reasontype' => 1]);

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_user_without_torrent_delete_permission_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('locked-iso', $owner->id);

        try {
            $this->post('/delete.php', [
                'id' => $torrentId,
                'reasontype' => 1,
            ])->assertForbidden();

            // Torrent still exists.
            $this->assertNotNull(NexusDB::table('torrents')->where('id', $torrentId)->first());
        } finally {
            NexusDB::table('torrents')->where('id', $torrentId)->delete();
        }
    }

    public function test_missing_id_renders_error_envelope(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $body = (string) $this->post('/delete.php', ['reasontype' => 1])->getContent();

        $this->assertStringContainsString('Delete Failed', $body);
        $this->assertStringContainsString('Missing form data', $body);
    }

    public function test_unknown_id_returns_404(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $maxId = (int) (NexusDB::table('torrents')->max('id') ?? 0);
        $bogus = $maxId + 9_999_999;

        $this->post('/delete.php', [
            'id' => $bogus,
            'reasontype' => 1,
        ])->assertNotFound();
    }

    public function test_invalid_reasontype_renders_error_envelope(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('any-iso', $owner->id);

        try {
            $body = (string) $this->post('/delete.php', [
                'id' => $torrentId,
                'reasontype' => 99,
            ])->getContent();

            $this->assertStringContainsString('Delete Failed', $body);
            $this->assertStringContainsString('Invalid reason type', $body);
            $this->assertNotNull(NexusDB::table('torrents')->where('id', $torrentId)->first());
        } finally {
            NexusDB::table('torrents')->where('id', $torrentId)->delete();
        }
    }

    public function test_rules_violation_requires_reason(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('rule-breaker', $owner->id);

        try {
            $body = (string) $this->post('/delete.php', [
                'id' => $torrentId,
                'reasontype' => 4,
                'reason' => ['', '', '', ''],
            ])->getContent();

            $this->assertStringContainsString('Delete Failed', $body);
            $this->assertNotNull(NexusDB::table('torrents')->where('id', $torrentId)->first());
        } finally {
            NexusDB::table('torrents')->where('id', $torrentId)->delete();
        }
    }

    public function test_other_reason_requires_text(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('other-reason', $owner->id);

        try {
            $body = (string) $this->post('/delete.php', [
                'id' => $torrentId,
                'reasontype' => 5,
                'reason' => ['', '', '', ''],
            ])->getContent();

            $this->assertStringContainsString('Delete Failed', $body);
            $this->assertNotNull(NexusDB::table('torrents')->where('id', $torrentId)->first());
        } finally {
            NexusDB::table('torrents')->where('id', $torrentId)->delete();
        }
    }

    public function test_admin_deleting_someone_elses_torrent_sends_pm(): void
    {
        $admin = $this->createTestUser([
            'class' => User::CLASS_ADMINISTRATOR,
            'username' => 'admin-'.bin2hex(random_bytes(3)),
        ]);
        $this->actingAs($admin, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('debian-iso', $owner->id);
        $this->seedTorrentFile($torrentId);

        $body = (string) $this->post('/delete.php', [
            'id' => $torrentId,
            'reasontype' => 2,
            'reason' => ['better quality available', '', '', ''],
        ])->getContent();

        $this->assertStringContainsString('Torrent Deleted', $body);
        $this->assertStringContainsString('Back to index', $body);
        $this->assertNull(NexusDB::table('torrents')->where('id', $torrentId)->first());

        $pm = Message::query()
            ->where('receiver', $owner->id)
            ->where('sender', 0)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($pm);
        $this->assertStringContainsString('debian-iso', (string) $pm->msg);
        $this->assertStringContainsString('Dupe: better quality available', (string) $pm->msg);
        $this->assertStringContainsString($admin->username, (string) $pm->msg);

        Message::query()->where('id', $pm->id)->delete();
    }

    public function test_owner_deleting_own_torrent_sends_no_pm(): void
    {
        $owner = $this->createTestUser([
            'class' => User::CLASS_ADMINISTRATOR,
            'username' => 'self-'.bin2hex(random_bytes(3)),
        ]);
        $this->actingAs($owner, 'nexus-web');

        $torrentId = $this->insertTorrent('mine-iso', $owner->id);
        $this->seedTorrentFile($torrentId);
        $pmsBefore = (int) Message::query()->where('sender', 0)->count();

        $body = (string) $this->post('/delete.php', [
            'id' => $torrentId,
            'reasontype' => 5,
            'reason' => ['', '', '', 'cleaning up old upload'],
            'returnto' => '/userdetails.php?id='.$owner->id,
        ])->getContent();

        $this->assertStringContainsString('Torrent Deleted', $body);
        $this->assertStringContainsString('userdetails.php?id='.$owner->id, $body);
        $this->assertNull(NexusDB::table('torrents')->where('id', $torrentId)->first());

        $pmsAfter = (int) Message::query()->where('sender', 0)->count();
        $this->assertSame($pmsBefore, $pmsAfter);
    }

    public function test_anonymous_self_delete_succeeds(): void
    {
        $owner = $this->createTestUser([
            'class' => User::CLASS_ADMINISTRATOR,
            'username' => 'anon-'.bin2hex(random_bytes(3)),
        ]);
        $this->actingAs($owner, 'nexus-web');

        $torrentId = $this->insertTorrent('anon-iso', $owner->id, [
            'anonymous' => 'yes',
        ]);
        $this->seedTorrentFile($torrentId);

        $body = (string) $this->post('/delete.php', [
            'id' => $torrentId,
            'reasontype' => 1,
        ])->getContent();

        $this->assertStringContainsString('Torrent Deleted', $body);
        $this->assertNull(NexusDB::table('torrents')->where('id', $torrentId)->first());
    }

    public function test_returnto_link_is_html_escaped(): void
    {
        $admin = $this->createTestUser([
            'class' => User::CLASS_ADMINISTRATOR,
            'username' => 'xss-admin-'.bin2hex(random_bytes(3)),
        ]);
        $this->actingAs($admin, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('xss-iso', $owner->id);
        $this->seedTorrentFile($torrentId);

        $body = (string) $this->post('/delete.php', [
            'id' => $torrentId,
            'reasontype' => 1,
            'returnto' => '"><script>alert(1)</script>',
        ])->getContent();

        $this->assertStringContainsString('Torrent Deleted', $body);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);

        $pm = Message::query()
            ->where('receiver', $owner->id)
            ->where('sender', 0)
            ->orderByDesc('id')
            ->first();
        if ($pm !== null) {
            Message::query()->where('id', $pm->id)->delete();
        }
    }

    /**
     * Regression guard: the karma deduction must read the legacy
     * `$GLOBALS['uploadtorrent_bonus']` (loaded by `include/config.php`
     * from `$BONUS['uploadtorrent']`) and call `KPS('-', $bonus, $owner)`.
     *
     * Before this PR, the controller called
     * `get_setting('bonus.per_uploaded_torrent')` which is not a known
     * setting key — it returned `null`, the `?: 0` fallback fired, and
     * `KPS('-', 0, ...)` was a no-op. As a result, deletes via the
     * edit-page form silently deducted nothing while the one-click
     * `fastdelete.php` sibling correctly deducted the bonus.
     *
     * This test pins the fixed contract: when `$uploadtorrent_bonus`
     * is non-zero, the owner's `seedbonus` decreases after the delete.
     */
    public function test_karma_is_deducted_from_owner(): void
    {
        $admin = $this->createTestUser([
            'class' => User::CLASS_ADMINISTRATOR,
            'username' => 'karma-admin-'.bin2hex(random_bytes(3)),
        ]);
        $this->actingAs($admin, 'nexus-web');

        $owner = $this->createTestUser([
            'username' => 'karma-owner-'.bin2hex(random_bytes(3)),
            'seedbonus' => '1000.0',
        ]);
        $torrentId = $this->insertTorrent('karma-iso', $owner->id);
        $this->seedTorrentFile($torrentId);

        $bonus = 7;
        $previousGlobal = $GLOBALS['uploadtorrent_bonus'] ?? null;
        $GLOBALS['uploadtorrent_bonus'] = $bonus;

        try {
            $body = (string) $this->post('/delete.php', [
                'id' => $torrentId,
                'reasontype' => 1,
            ])->getContent();

            $this->assertStringContainsString('Torrent Deleted', $body);

            $bonusAfter = (float) NexusDB::table('users')
                ->where('id', $owner->id)
                ->value('seedbonus');
            $this->assertEqualsWithDelta(
                1000.0 - $bonus,
                $bonusAfter,
                0.001,
                'Owner seedbonus should have been decreased by $uploadtorrent_bonus '
                .'after a successful delete (regression: pre-fix the controller used '
                .'a non-existent `bonus.per_uploaded_torrent` setting and deducted 0).'
            );
        } finally {
            if ($previousGlobal === null) {
                unset($GLOBALS['uploadtorrent_bonus']);
            } else {
                $GLOBALS['uploadtorrent_bonus'] = $previousGlobal;
            }

            $pm = Message::query()
                ->where('receiver', $owner->id)
                ->where('sender', 0)
                ->orderByDesc('id')
                ->first();
            if ($pm !== null) {
                Message::query()->where('id', $pm->id)->delete();
            }
        }
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function insertTorrent(string $name, int $ownerId, array $overrides = []): int
    {
        return (int) NexusDB::table('torrents')->insertGetId(array_merge([
            'name' => $name,
            'filename' => $name.'.torrent',
            'owner' => $ownerId,
            'info_hash' => hex2bin(str_pad(bin2hex(random_bytes(10)), 40, '0')),
            'anonymous' => 'no',
            'added' => Carbon::now()->toDateTimeString(),
        ], $overrides));
    }

    private function seedTorrentFile(int $torrentId): void
    {
        $dir = (string) get_setting('main.torrent_dir');
        $base = is_dir($dir) ? $dir : ROOT_PATH.$dir;
        if (! is_dir($base)) {
            mkdir($base, 0o755, true);
        }
        $path = $base.'/'.$torrentId.'.torrent';
        file_put_contents($path, 'dummy');
        $this->createdTorrentFiles[] = $path;
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
