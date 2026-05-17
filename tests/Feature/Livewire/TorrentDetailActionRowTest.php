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

class TorrentDetailActionRowTest extends FeatureTestCase
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

    public function test_action_row_is_hidden_for_guests(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSeeHtml('data-test-id="torrent-action-row"');
    }

    public function test_member_sees_download_and_report_but_no_edit_or_reseed(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser(['class' => User::CLASS_USER]);
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-test-id="torrent-action-row"')
            ->assertSeeHtml('data-test-id="download-btn"')
            ->assertSeeHtml('data-test-id="action-report"')
            ->assertDontSeeHtml('data-test-id="action-edit"')
            ->assertDontSeeHtml('data-test-id="action-reseed"')
            ->assertSeeHtml('href="/download.php?id='.$torrentId.'"')
            ->assertSeeHtml('href="/report.php?torrent='.$torrentId.'"');
    }

    public function test_owner_sees_edit_button_with_plain_label(): void
    {
        $owner = $this->createUser(['class' => User::CLASS_USER]);
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-test-id="action-edit"')
            ->assertSeeHtml('href="/edit.php?id='.$torrentId.'"')
            ->assertSee('Edit')
            ->assertDontSee('Edit / delete');
    }

    public function test_staff_with_torrentmanage_sees_edit_delete_label(): void
    {
        $owner = $this->createUser(['class' => User::CLASS_USER]);
        $staff = $this->createUser(['class' => User::CLASS_MODERATOR]);
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($staff, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-test-id="action-edit"')
            ->assertSee('Edit / delete');
    }

    public function test_reseed_button_appears_only_when_seeders_are_zero(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser(['class' => User::CLASS_POWER_USER]);
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-test-id="action-reseed"')
            ->assertSeeHtml('href="/takereseed.php?reseedid='.$torrentId.'"');
    }

    public function test_reseed_button_is_hidden_when_torrent_has_seeders(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser(['class' => User::CLASS_POWER_USER]);
        $torrentId = $this->createTorrent($owner->id, ['seeders' => 3]);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSeeHtml('data-test-id="action-reseed"');
    }

    public function test_reseed_button_is_hidden_when_viewer_lacks_askreseed(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser(['class' => User::CLASS_PEASANT]);
        $torrentId = $this->createTorrent($owner->id, ['seeders' => 0]);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSeeHtml('data-test-id="action-reseed"');
    }

    public function test_download_button_is_hidden_when_viewer_downloadpos_is_no(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser([
            'class' => User::CLASS_USER,
            'downloadpos' => 'no',
        ]);
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSeeHtml('data-test-id="download-btn"')
            ->assertSeeHtml('data-test-id="action-report"');
    }

    public function test_owner_with_downloadpos_no_still_sees_download_button(): void
    {
        $owner = $this->createUser([
            'class' => User::CLASS_USER,
            'downloadpos' => 'no',
        ]);
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-test-id="download-btn"');
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
            'name' => 'action-row-test-'.bin2hex(random_bytes(4)),
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
}
