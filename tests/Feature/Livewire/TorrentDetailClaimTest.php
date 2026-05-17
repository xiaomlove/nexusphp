<?php

namespace Tests\Feature\Livewire;

use App\Livewire\TorrentDetail;
use App\Models\Claim;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class TorrentDetailClaimTest extends FeatureTestCase
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

        $this->seedSetting('torrent.claim_enabled', 'yes');
        $this->seedSetting('torrent.claim_torrent_ttl', '30');
        $this->seedSetting('torrent.claim_torrent_user_counts_up_limit', '5');
    }

    public function test_claim_block_is_hidden_for_guests(): void
    {
        $torrentId = $this->createTorrent($this->createUser()->id, [
            'added' => Carbon::now()->subDays(60)->toDateTimeString(),
        ]);

        Livewire::test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSeeHtml('data-test-id="claim-block"');
    }

    public function test_claim_block_is_hidden_when_feature_disabled(): void
    {
        $this->seedSetting('torrent.claim_enabled', 'no');

        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($this->createUser()->id, [
            'added' => Carbon::now()->subDays(60)->toDateTimeString(),
        ]);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSeeHtml('data-test-id="claim-block"');
    }

    public function test_claim_block_is_hidden_when_torrent_is_younger_than_ttl(): void
    {
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($this->createUser()->id, [
            'added' => Carbon::now()->subDays(5)->toDateTimeString(),
        ]);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSeeHtml('data-test-id="claim-block"');
    }

    public function test_claim_block_renders_open_button_when_viewer_has_not_claimed(): void
    {
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($this->createUser()->id, [
            'added' => Carbon::now()->subDays(60)->toDateTimeString(),
        ]);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-test-id="claim-block"')
            ->assertSeeHtml('data-claim-state="open"')
            ->assertSeeHtml('data-test-id="claim-button"')
            ->assertSeeHtml('wire:click="addClaim"')
            ->assertSee('Claim')
            ->assertDontSee('Claimed')
            ->assertSeeHtml('href="/claim.php?torrent_id='.$torrentId.'"');
    }

    public function test_claim_block_renders_disabled_state_when_viewer_already_claimed(): void
    {
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($this->createUser()->id, [
            'added' => Carbon::now()->subDays(60)->toDateTimeString(),
        ]);
        $this->insertSnatchAndClaim($viewer->id, $torrentId);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-test-id="claim-block"')
            ->assertSeeHtml('data-claim-state="claimed"')
            ->assertSee('Claimed')
            ->assertDontSeeHtml('wire:click="addClaim"');
    }

    public function test_claim_info_renders_counts_and_remaining_slots(): void
    {
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($this->createUser()->id, [
            'added' => Carbon::now()->subDays(60)->toDateTimeString(),
        ]);

        $other1 = $this->createUser();
        $other2 = $this->createUser();
        $this->insertSnatchAndClaim($other1->id, $torrentId);
        $this->insertSnatchAndClaim($other2->id, $torrentId);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-claim-count="2"')
            ->assertSeeHtml('data-claim-remaining="3"');
    }

    public function test_add_claim_stores_claim_for_snatched_user(): void
    {
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($this->createUser()->id, [
            'added' => Carbon::now()->subDays(60)->toDateTimeString(),
        ]);
        $this->insertSnatch($viewer->id, $torrentId);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->call('addClaim')
            ->assertHasNoErrors()
            ->assertSet('claimFlash', 'Claim recorded.');

        $this->assertSame(
            1,
            Claim::query()->where('torrent_id', $torrentId)->where('uid', $viewer->id)->count(),
        );
    }

    public function test_add_claim_rejects_viewer_without_snatch(): void
    {
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($this->createUser()->id, [
            'added' => Carbon::now()->subDays(60)->toDateTimeString(),
        ]);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->call('addClaim')
            ->assertHasErrors('claim');

        $this->assertSame(
            0,
            Claim::query()->where('torrent_id', $torrentId)->count(),
        );
    }

    public function test_add_claim_is_silent_for_guests(): void
    {
        $torrentId = $this->createTorrent($this->createUser()->id, [
            'added' => Carbon::now()->subDays(60)->toDateTimeString(),
        ]);

        Livewire::test(TorrentDetail::class, ['id' => $torrentId])
            ->call('addClaim');

        $this->assertSame(0, Claim::query()->where('torrent_id', $torrentId)->count());
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge([
                'lang' => self::ENGLISH_LANGUAGE_ID,
                'class' => User::CLASS_USER,
            ], $overrides),
        );
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTorrent(int $ownerId, array $overrides = []): int
    {
        return (int) NexusDB::table('torrents')->insertGetId(array_merge([
            'name' => 'claim-test-'.bin2hex(random_bytes(4)),
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

    private function insertSnatch(int $userId, int $torrentId): int
    {
        return (int) NexusDB::table('snatched')->insertGetId([
            'torrentid' => $torrentId,
            'userid' => $userId,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 0,
            'seedtime' => 0,
            'leechtime' => 0,
            'startdat' => Carbon::now()->toDateTimeString(),
            'last_action' => Carbon::now()->toDateTimeString(),
            'ip' => '127.0.0.1',
            'port' => 0,
            'finished' => 'yes',
            'completedat' => Carbon::now()->toDateTimeString(),
        ]);
    }

    private function insertSnatchAndClaim(int $userId, int $torrentId): void
    {
        $snatchId = $this->insertSnatch($userId, $torrentId);
        NexusDB::table('claims')->insert([
            'uid' => $userId,
            'torrent_id' => $torrentId,
            'snatched_id' => $snatchId,
            'seed_time_begin' => 0,
            'uploaded_begin' => 0,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
    }

    private function seedSetting(string $name, string $value): void
    {
        $now = Carbon::now()->toDateTimeString();
        NexusDB::table('settings')->updateOrInsert(
            ['name' => $name],
            [
                'value' => $value,
                'autoload' => 'yes',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }
}
