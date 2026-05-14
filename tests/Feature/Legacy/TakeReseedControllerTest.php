<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/takereseed.php?reseedid=<id>` contract.
 *
 * Power-user+ only PM fan-out that asks every finished snatcher of a
 * dead torrent to start seeding it again. The legacy script used
 * `user_can('askreseed', true)` which `stderr()`'d at HTTP 200 for
 * non-qualifying users; we tighten to a real 403 in line with the
 * rest of Phase 2 (see `DonorlistControllerTest`).
 *
 * The "ask reseed" feature is gated by the legacy
 * `$AUTHORITY['askreseed']` knob in `config/allconfig.php`, which
 * defaults to `2` (Power User). Tests use `User::CLASS_USER` (`1`)
 * for the "forbidden" assertions and any class `>= 2` for the
 * happy-path / cooldown / not-dead branches.
 */
class TakeReseedControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/takereseed.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/takereseed.php?reseedid=1');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_below_threshold_user_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/takereseed.php?reseedid=1')->assertForbidden();
    }

    public function test_invalid_reseedid_returns_422_with_error_body(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_POWER_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/takereseed.php');

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'Invalid reseed id',
            (string) $response->getContent(),
        );
    }

    public function test_unknown_torrent_returns_404(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_POWER_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/takereseed.php?reseedid=9999999');

        $response->assertStatus(404);
        $this->assertStringContainsString(
            'Invalid torrent',
            (string) $response->getContent(),
        );
    }

    public function test_torrent_with_live_peers_is_rejected_with_not_dead_notice(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_POWER_USER]);
        $this->actingAs($user, 'nexus-web');

        $torrentId = $this->createTorrent($user->id);
        $peerId = $this->insertPeer($torrentId, 100);

        try {
            $response = $this->get('/takereseed.php?reseedid='.$torrentId);

            $response->assertOk();
            $this->assertStringContainsString(
                'torrent is not really dead',
                (string) $response->getContent(),
            );
        } finally {
            NexusDB::table('peers')->where('id', $peerId)->delete();
        }
    }

    public function test_recent_reseed_request_returns_cooldown_notice(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_POWER_USER]);
        $this->actingAs($user, 'nexus-web');

        $torrentId = $this->createTorrent($user->id);
        NexusDB::table('torrents')->where('id', $torrentId)->update([
            'last_reseed' => Carbon::now()->subMinutes(5)->toDateTimeString(),
        ]);

        $response = $this->get('/takereseed.php?reseedid='.$torrentId);

        $response->assertOk();
        $this->assertStringContainsString(
            'sent recently',
            (string) $response->getContent(),
        );
    }

    public function test_happy_path_sends_pm_and_stamps_last_reseed(): void
    {
        $rewarder = $this->createTestUser([
            'class' => User::CLASS_POWER_USER,
            'username' => 'reseeder_'.bin2hex(random_bytes(3)),
        ]);
        $this->actingAs($rewarder, 'nexus-web');

        $snatcher = $this->createTestUser([
            'username' => 'snatcher_'.bin2hex(random_bytes(3)),
        ]);

        $torrentId = $this->createTorrent($rewarder->id);
        $this->insertSnatched($torrentId, $snatcher->id, 'yes');

        // A snatch that hasn't finished must be excluded from the
        // fan-out: only `finished='yes'` rows get the PM.
        $bystander = $this->createTestUser([
            'username' => 'leecher_'.bin2hex(random_bytes(3)),
        ]);
        $this->insertSnatched($torrentId, $bystander->id, 'no');

        $beforeReseed = NexusDB::table('torrents')
            ->where('id', $torrentId)
            ->value('last_reseed');

        $response = $this->get('/takereseed.php?reseedid='.$torrentId);

        $response->assertOk();
        $this->assertStringContainsString(
            'It worked',
            (string) $response->getContent(),
        );

        // PM landed in `messages` for the finished snatcher only.
        $this->assertDatabaseHas('messages', [
            'sender' => 0,
            'receiver' => $snatcher->id,
        ]);
        $this->assertDatabaseMissing('messages', [
            'sender' => 0,
            'receiver' => $bystander->id,
        ]);

        // `torrents.last_reseed` was stamped (legacy: `now()`).
        $afterReseed = NexusDB::table('torrents')
            ->where('id', $torrentId)
            ->value('last_reseed');
        $this->assertNotSame($beforeReseed, $afterReseed);
        $this->assertNotEmpty($afterReseed);
    }

    public function test_pm_body_includes_rewarder_username_and_torrent_link(): void
    {
        $rewarder = $this->createTestUser([
            'class' => User::CLASS_POWER_USER,
            'username' => 'rewardpm_'.bin2hex(random_bytes(3)),
        ]);
        $this->actingAs($rewarder, 'nexus-web');

        $snatcher = $this->createTestUser();
        $torrentId = $this->createTorrent($rewarder->id);
        $this->insertSnatched($torrentId, $snatcher->id, 'yes');

        $this->get('/takereseed.php?reseedid='.$torrentId)->assertOk();

        $pmBody = (string) NexusDB::table('messages')
            ->where('receiver', $snatcher->id)
            ->orderByDesc('id')
            ->value('msg');

        $this->assertStringContainsString($rewarder->username, $pmBody);
        $this->assertStringContainsString('details.php?id='.$torrentId, $pmBody);
    }

    /**
     * Insert a minimal torrent row owned by `$ownerId`. Returns the
     * new `torrents.id`. Cleanup is automatic — the surrounding test
     * runs inside a `DatabaseTransactions` rollback.
     */
    private function createTorrent(int $ownerId): int
    {
        return (int) NexusDB::table('torrents')->insertGetId([
            'name' => 'reseed-test-'.bin2hex(random_bytes(4)),
            'filename' => 'fixture.torrent',
            'save_as' => 'fixture',
            'cover' => '',
            'small_descr' => '',
            'owner' => $ownerId,
            'added' => Carbon::now()->toDateTimeString(),
            'pieces_hash' => str_repeat('0', 40),
            'seeders' => 0,
            'leechers' => 0,
        ]);
    }

    /**
     * Insert a `peers` row so {@see TakeReseedController} sees the
     * torrent as alive. Returns the new `peers.id` so the caller can
     * clean it up if needed (peers table is not transaction-isolated
     * the same way other tables are, in MySQL InnoDB).
     */
    private function insertPeer(int $torrentId, int $userId): int
    {
        return (int) NexusDB::table('peers')->insertGetId([
            'torrent' => $torrentId,
            'userid' => $userId,
            'peer_id' => str_repeat('a', 20),
            'ip' => '127.0.0.1',
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 0,
            'started' => Carbon::now()->toDateTimeString(),
            'last_action' => Carbon::now()->toDateTimeString(),
            'seeder' => 'yes',
            'agent' => 'test',
            'passkey' => str_repeat('b', 32),
            'connectable' => 'yes',
            'uploadoffset' => 0,
            'downloadoffset' => 0,
            'prev_amount_uploaded' => 0,
            'prev_amount_downloaded' => 0,
        ]);
    }

    /**
     * Insert a `snatched` row so the controller picks the user up in
     * its PM fan-out (when `finished='yes'`) or excludes them (when
     * `finished='no'`). Returns the new `snatched.id`.
     */
    private function insertSnatched(int $torrentId, int $userId, string $finished): int
    {
        return (int) NexusDB::table('snatched')->insertGetId([
            'torrentid' => $torrentId,
            'userid' => $userId,
            'ip' => '127.0.0.1',
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 0,
            'seedtime' => 0,
            'leechtime' => 0,
            'last_action' => Carbon::now()->toDateTimeString(),
            'startdat' => Carbon::now()->toDateTimeString(),
            'completedat' => Carbon::now()->toDateTimeString(),
            'finished' => $finished,
        ]);
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
