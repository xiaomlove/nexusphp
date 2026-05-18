<?php

namespace Tests\Feature\Livewire;

use App\Livewire\TorrentDetail;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins the Phase 3 — Modern UI A3 contract for the Files + Peers
 * tabs on `App\Livewire\TorrentDetail` (PR C of the `details.php`
 * Strangler-Fig series).
 *
 * Covers:
 *
 *   - Files card: empty state vs. populated table.
 *   - Peers card: empty seeders + empty leechers fall back to a
 *     "no <role>" caption.
 *   - Peers split: rows with `seeder = 'yes'` land in the seeders
 *     section, `seeder = 'no'` in the leechers section.
 *   - Anonymity:
 *       * peer whose user has `privacy = 'strong'` is rendered as
 *         "Anonymous" for a regular viewer.
 *       * staff leader (carrying `viewanonymous`) sees the real
 *         username for the same row.
 *       * the user themselves always sees their own username (own
 *         row override).
 *       * when `torrent.anonymous = 'yes'`, the uploader row is
 *         anonymised for regular viewers, even with `privacy = 'normal'`.
 */
class TorrentDetailFilesAndPeersTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    private int $categoryId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/torrent/0';

        $this->categoryId = (int) NexusDB::table('categories')->insertGetId([
            'mode' => 0,
            'class_name' => 'c_test',
            'name' => 'TestCat-'.bin2hex(random_bytes(2)),
            'image' => '',
            'sort_index' => 0,
            'icon_id' => 0,
        ]);
    }

    public function test_files_card_shows_empty_state_when_no_rows_exist(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSee('data-test-id="files-empty"', false)
            ->assertDontSee('data-test-id="files-table"', false)
            ->assertSee('Files (0)', false);
    }

    public function test_files_card_renders_table_rows_for_each_file(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        $fileIds = [];
        $fileIds[] = $this->insertFile($torrentId, 'movie.mkv', 4 * 1024 * 1024 * 1024);
        $fileIds[] = $this->insertFile($torrentId, 'subs/movie.eng.srt', 12 * 1024);
        $fileIds[] = $this->insertFile($torrentId, 'readme.txt', 256);

        $component = Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSee('data-test-id="files-table"', false)
            ->assertDontSee('data-test-id="files-empty"', false)
            ->assertSee('Files (3)', false)
            ->assertSee('movie.mkv')
            ->assertSee('subs/movie.eng.srt')
            ->assertSee('readme.txt');

        foreach ($fileIds as $fileId) {
            $component->assertSee('data-file-id="'.$fileId.'"', false);
        }
    }

    public function test_peer_sections_show_empty_states_when_no_peers_exist(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSee('data-test-id="peers-seeders-empty"', false)
            ->assertSee('data-test-id="peers-leechers-empty"', false)
            ->assertSee('Seeders (0)', false)
            ->assertSee('Leechers (0)', false);
    }

    public function test_peer_sections_expose_seeders_and_leechers_fragment_anchors(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSee('id="seeders"', false)
            ->assertSee('id="leechers"', false);
    }

    public function test_peer_rows_split_between_seeders_and_leechers(): void
    {
        $owner = $this->createUser();
        $seederUser = $this->createUser();
        $leecherUser = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        $seederPeerId = $this->insertPeer($torrentId, $seederUser->id, ['seeder' => 'yes']);
        $leecherPeerId = $this->insertPeer($torrentId, $leecherUser->id, ['seeder' => 'no']);

        try {
            Livewire::actingAs($owner, 'nexus-web')
                ->test(TorrentDetail::class, ['id' => $torrentId])
                ->assertSee('Seeders (1)', false)
                ->assertSee('Leechers (1)', false)
                ->assertSee('data-peer-id="'.$seederPeerId.'"', false)
                ->assertSee('data-peer-id="'.$leecherPeerId.'"', false)
                ->assertSee($seederUser->username)
                ->assertSee($leecherUser->username);
        } finally {
            NexusDB::table('peers')->whereIn('id', [$seederPeerId, $leecherPeerId])->delete();
        }
    }

    public function test_strong_privacy_hides_username_from_regular_viewer(): void
    {
        $owner = $this->createUser();
        $shyUser = $this->createUser(['privacy' => 'strong']);
        $viewer = $this->createUser(['class' => User::CLASS_USER]);
        $torrentId = $this->createTorrent($owner->id);

        $peerId = $this->insertPeer($torrentId, $shyUser->id, ['seeder' => 'yes']);

        try {
            Livewire::actingAs($viewer, 'nexus-web')
                ->test(TorrentDetail::class, ['id' => $torrentId])
                ->assertSee('data-peer-id="'.$peerId.'"', false)
                ->assertDontSee($shyUser->username)
                ->assertSee('Anonymous');
        } finally {
            NexusDB::table('peers')->where('id', $peerId)->delete();
        }
    }

    public function test_strong_privacy_user_sees_their_own_username(): void
    {
        $owner = $this->createUser();
        $shyUser = $this->createUser(['privacy' => 'strong']);
        $torrentId = $this->createTorrent($owner->id);

        $peerId = $this->insertPeer($torrentId, $shyUser->id, ['seeder' => 'yes']);

        try {
            Livewire::actingAs($shyUser, 'nexus-web')
                ->test(TorrentDetail::class, ['id' => $torrentId])
                ->assertSee('data-peer-id="'.$peerId.'"', false)
                ->assertSee($shyUser->username);
        } finally {
            NexusDB::table('peers')->where('id', $peerId)->delete();
        }
    }

    public function test_staff_leader_sees_strong_privacy_usernames(): void
    {
        $owner = $this->createUser();
        $shyUser = $this->createUser(['privacy' => 'strong']);
        $staff = $this->createUser(['class' => User::CLASS_STAFF_LEADER]);
        $torrentId = $this->createTorrent($owner->id);

        $peerId = $this->insertPeer($torrentId, $shyUser->id, ['seeder' => 'yes']);

        try {
            Livewire::actingAs($staff, 'nexus-web')
                ->test(TorrentDetail::class, ['id' => $torrentId])
                ->assertSee('data-peer-id="'.$peerId.'"', false)
                ->assertSee($shyUser->username);
        } finally {
            NexusDB::table('peers')->where('id', $peerId)->delete();
        }
    }

    public function test_anonymous_torrent_hides_uploader_peer_username(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser(['class' => User::CLASS_USER]);
        $torrentId = $this->createTorrent($owner->id, ['anonymous' => 'yes']);

        $peerId = $this->insertPeer($torrentId, $owner->id, ['seeder' => 'yes']);

        try {
            Livewire::actingAs($viewer, 'nexus-web')
                ->test(TorrentDetail::class, ['id' => $torrentId])
                ->assertSee('data-peer-id="'.$peerId.'"', false)
                ->assertDontSee($owner->username)
                ->assertSee('Anonymous');
        } finally {
            NexusDB::table('peers')->where('id', $peerId)->delete();
        }
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createUser(array $overrides = []): User
    {
        $privacy = $overrides['privacy'] ?? null;
        unset($overrides['privacy']);

        $user = $this->createLegacyUser(
            overrides: array_merge([
                'lang' => self::ENGLISH_LANGUAGE_ID,
            ], $overrides),
        );

        // `privacy` is not in `User::$fillable`, so mass-assignment would
        // silently drop it. Apply it via direct DB update to mirror the
        // legacy on-disk schema (`users.privacy` enum).
        if ($privacy !== null) {
            NexusDB::table('users')->where('id', $user->id)->update(['privacy' => $privacy]);
            $user->refresh();
        }

        return $user;
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTorrent(int $ownerId, array $overrides = []): int
    {
        return (int) NexusDB::table('torrents')->insertGetId(array_merge([
            'name' => 'detail-test-'.bin2hex(random_bytes(4)),
            'filename' => 'fixture.torrent',
            'save_as' => 'fixture',
            'cover' => '',
            'small_descr' => '',
            'owner' => $ownerId,
            'added' => Carbon::now()->toDateTimeString(),
            'pieces_hash' => str_repeat('0', 40),
            'category' => $this->categoryId,
            'banned' => Torrent::BANNED_NO,
            'visible' => Torrent::VISIBLE_YES,
            'sp_state' => Torrent::PROMOTION_NORMAL,
            'comments' => 0,
            'size' => 1024,
            'seeders' => 0,
            'leechers' => 0,
            'times_completed' => 0,
            'approval_status' => Torrent::APPROVAL_STATUS_ALLOW,
        ], $overrides));
    }

    private function insertFile(int $torrentId, string $filename, int $size): int
    {
        return (int) NexusDB::table('files')->insertGetId([
            'torrent' => $torrentId,
            'filename' => $filename,
            'size' => $size,
        ]);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function insertPeer(int $torrentId, int $userId, array $overrides = []): int
    {
        return (int) NexusDB::table('peers')->insertGetId(array_merge([
            'torrent' => $torrentId,
            'userid' => $userId,
            'peer_id' => random_bytes(20),
            'ip' => '127.0.0.1',
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 0,
            'started' => Carbon::now()->toDateTimeString(),
            'last_action' => Carbon::now()->toDateTimeString(),
            'seeder' => 'yes',
            'agent' => 'test',
            'passkey' => bin2hex(random_bytes(16)),
            'connectable' => 'yes',
            'uploadoffset' => 0,
            'downloadoffset' => 0,
        ], $overrides));
    }
}
