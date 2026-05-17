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

class TorrentDetailHitTest extends FeatureTestCase
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
            'class_name' => 'c_hit_test',
            'name' => 'HitCat-'.bin2hex(random_bytes(2)),
            'image' => '',
            'sort_index' => 0,
            'icon_id' => 0,
        ]);
    }

    public function test_hit_query_increments_views_once(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($owner->id, ['views' => 5]);

        Livewire::actingAs($viewer, 'nexus-web')
            ->withQueryParams(['hit' => '1'])
            ->test(TorrentDetail::class, ['id' => $torrentId]);

        $this->assertSame(
            6,
            (int) NexusDB::table('torrents')->where('id', $torrentId)->value('views'),
        );
    }

    public function test_hit_query_increments_views_for_torrent_owner(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id, ['views' => 5]);

        Livewire::actingAs($owner, 'nexus-web')
            ->withQueryParams(['hit' => '1'])
            ->test(TorrentDetail::class, ['id' => $torrentId]);

        $this->assertSame(
            6,
            (int) NexusDB::table('torrents')->where('id', $torrentId)->value('views'),
        );
    }

    public function test_no_hit_query_does_not_increment_views(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($owner->id, ['views' => 5]);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId]);

        $this->assertSame(
            5,
            (int) NexusDB::table('torrents')->where('id', $torrentId)->value('views'),
        );
    }

    public function test_empty_hit_query_does_not_increment_views(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($owner->id, ['views' => 5]);

        Livewire::actingAs($viewer, 'nexus-web')
            ->withQueryParams(['hit' => ''])
            ->test(TorrentDetail::class, ['id' => $torrentId]);

        $this->assertSame(
            5,
            (int) NexusDB::table('torrents')->where('id', $torrentId)->value('views'),
        );
    }

    public function test_hit_query_does_not_increment_when_torrent_is_404(): void
    {
        $viewer = $this->createUser();

        $this->actingAs($viewer, 'nexus-web');

        $this->get('/torrent/999999?hit=1')->assertNotFound();
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
            'name' => 'detail-hit-'.bin2hex(random_bytes(4)),
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
