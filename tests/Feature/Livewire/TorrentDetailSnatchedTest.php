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
 * Pins the Phase 3 — Modern UI A3 contract for the Snatched tab on
 * `App\Livewire\TorrentDetail` (PR D of the `details.php` Strangler-Fig
 * series).
 *
 * Covers:
 *
 *   - Snatched card: empty state when no finished snatches exist,
 *     populated state when there are.
 *   - Only `finished = 'yes'` snatches are surfaced (`finished = 'no'`
 *     and snatches against a different torrent are filtered out).
 *   - Ordering: most-recent `completedat` first.
 *   - Anonymity (mirrors legacy `public/viewsnatches.php`):
 *       * `users.privacy = 'strong'` hides the username from regular
 *         viewers ("Anonymous"),
 *       * the strong-privacy user themselves still sees their own
 *         username (own-row override),
 *       * staff leader (carrying `viewanonymous`) sees the real
 *         username for any row.
 */
class TorrentDetailSnatchedTest extends FeatureTestCase
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

    public function test_snatched_card_shows_empty_state_when_no_finished_snatches(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        // An unfinished snatch on the same torrent must NOT show up.
        $unfinished = $this->insertSnatch($torrentId, $this->createUser()->id, ['finished' => 'no']);

        try {
            Livewire::actingAs($owner, 'nexus-web')
                ->test(TorrentDetail::class, ['id' => $torrentId])
                ->assertSee('data-test-id="snatches-empty"', false)
                ->assertDontSee('data-test-id="snatches-table"', false)
                ->assertSee('Snatched (0)', false);
        } finally {
            NexusDB::table('snatched')->where('id', $unfinished)->delete();
        }
    }

    public function test_snatched_card_renders_only_finished_rows(): void
    {
        $owner = $this->createUser();
        $finisher = $this->createUser();
        $unfinishedUser = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);
        $otherTorrentId = $this->createTorrent($owner->id);

        $finished = $this->insertSnatch($torrentId, $finisher->id, [
            'finished' => 'yes',
            'completedat' => Carbon::now()->toDateTimeString(),
        ]);
        $unfinished = $this->insertSnatch($torrentId, $unfinishedUser->id, ['finished' => 'no']);
        $otherTorrent = $this->insertSnatch($otherTorrentId, $finisher->id, ['finished' => 'yes']);

        try {
            Livewire::actingAs($owner, 'nexus-web')
                ->test(TorrentDetail::class, ['id' => $torrentId])
                ->assertSee('Snatched (1)', false)
                ->assertSee('data-test-id="snatches-table"', false)
                ->assertSee('data-snatch-id="'.$finished.'"', false)
                ->assertDontSee('data-snatch-id="'.$unfinished.'"', false)
                ->assertDontSee('data-snatch-id="'.$otherTorrent.'"', false)
                ->assertSee($finisher->username);
        } finally {
            NexusDB::table('snatched')->whereIn('id', [$finished, $unfinished, $otherTorrent])->delete();
        }
    }

    public function test_snatched_rows_ordered_by_completedat_desc(): void
    {
        $owner = $this->createUser();
        $oldUser = $this->createUser();
        $newUser = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        $old = $this->insertSnatch($torrentId, $oldUser->id, [
            'finished' => 'yes',
            'completedat' => Carbon::now()->subDays(3)->toDateTimeString(),
        ]);
        $new = $this->insertSnatch($torrentId, $newUser->id, [
            'finished' => 'yes',
            'completedat' => Carbon::now()->toDateTimeString(),
        ]);

        try {
            $html = Livewire::actingAs($owner, 'nexus-web')
                ->test(TorrentDetail::class, ['id' => $torrentId])
                ->html();

            $newPos = strpos($html, 'data-snatch-id="'.$new.'"');
            $oldPos = strpos($html, 'data-snatch-id="'.$old.'"');

            $this->assertNotFalse($newPos, 'newer snatch row should be rendered');
            $this->assertNotFalse($oldPos, 'older snatch row should be rendered');
            $this->assertLessThan($oldPos, $newPos, 'newer snatch row should appear before older one');
        } finally {
            NexusDB::table('snatched')->whereIn('id', [$old, $new])->delete();
        }
    }

    public function test_strong_privacy_hides_snatch_username_from_regular_viewer(): void
    {
        $owner = $this->createUser();
        $shyUser = $this->createUserWithPrivacy('strong');
        $viewer = $this->createUser(['class' => User::CLASS_USER]);
        $torrentId = $this->createTorrent($owner->id);

        $snatchId = $this->insertSnatch($torrentId, $shyUser->id, ['finished' => 'yes']);

        try {
            Livewire::actingAs($viewer, 'nexus-web')
                ->test(TorrentDetail::class, ['id' => $torrentId])
                ->assertSee('data-snatch-id="'.$snatchId.'"', false)
                ->assertDontSee($shyUser->username)
                ->assertSee('Anonymous');
        } finally {
            NexusDB::table('snatched')->where('id', $snatchId)->delete();
        }
    }

    public function test_strong_privacy_user_sees_their_own_snatch_username(): void
    {
        $owner = $this->createUser();
        $shyUser = $this->createUserWithPrivacy('strong');
        $torrentId = $this->createTorrent($owner->id);

        $snatchId = $this->insertSnatch($torrentId, $shyUser->id, ['finished' => 'yes']);

        try {
            Livewire::actingAs($shyUser, 'nexus-web')
                ->test(TorrentDetail::class, ['id' => $torrentId])
                ->assertSee('data-snatch-id="'.$snatchId.'"', false)
                ->assertSee($shyUser->username);
        } finally {
            NexusDB::table('snatched')->where('id', $snatchId)->delete();
        }
    }

    public function test_staff_leader_sees_strong_privacy_snatch_usernames(): void
    {
        $owner = $this->createUser();
        $shyUser = $this->createUserWithPrivacy('strong');
        $staff = $this->createUser(['class' => User::CLASS_STAFF_LEADER]);
        $torrentId = $this->createTorrent($owner->id);

        $snatchId = $this->insertSnatch($torrentId, $shyUser->id, ['finished' => 'yes']);

        try {
            Livewire::actingAs($staff, 'nexus-web')
                ->test(TorrentDetail::class, ['id' => $torrentId])
                ->assertSee('data-snatch-id="'.$snatchId.'"', false)
                ->assertSee($shyUser->username);
        } finally {
            NexusDB::table('snatched')->where('id', $snatchId)->delete();
        }
    }

    private function createUserWithPrivacy(string $privacy): User
    {
        $user = $this->createUser();
        // `privacy` is not in `User::$fillable`, so mass-assignment would
        // silently drop it. Apply it via direct DB update to mirror the
        // legacy on-disk schema (`users.privacy` enum).
        NexusDB::table('users')->where('id', $user->id)->update(['privacy' => $privacy]);
        $user->refresh();

        return $user;
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge([
                'lang' => self::ENGLISH_LANGUAGE_ID,
            ], $overrides),
        );
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

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function insertSnatch(int $torrentId, int $userId, array $overrides = []): int
    {
        return (int) NexusDB::table('snatched')->insertGetId(array_merge([
            'torrentid' => $torrentId,
            'userid' => $userId,
            'ip' => '127.0.0.1',
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 0,
            'seedtime' => 0,
            'leechtime' => 0,
            'startdat' => Carbon::now()->toDateTimeString(),
            'completedat' => Carbon::now()->toDateTimeString(),
            'last_action' => Carbon::now()->toDateTimeString(),
            'finished' => 'yes',
        ], $overrides));
    }
}
