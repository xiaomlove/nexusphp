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

class TorrentDetailPostWriteBannerTest extends FeatureTestCase
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

    public function test_banner_is_absent_without_post_write_query_param(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSeeHtml('data-test-id="post-write-banner"');
    }

    public function test_uploaded_banner_renders_with_redownload_hint(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($owner, 'nexus-web')
            ->withQueryParams(['uploaded' => '1'])
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-test-id="post-write-banner"')
            ->assertSeeHtml('data-banner-type="uploaded"')
            ->assertSee('Successfully uploaded.')
            ->assertSeeHtml('re-download')
            ->assertDontSeeHtml('data-test-id="post-write-returnto"');
    }

    public function test_edited_banner_renders_returnto_link_when_present(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($owner, 'nexus-web')
            ->withQueryParams(['edited' => '1', 'returnto' => '/usercp.php?action=mytorrents'])
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-test-id="post-write-banner"')
            ->assertSeeHtml('data-banner-type="edited"')
            ->assertSee('Successfully edited.')
            ->assertSeeHtml('data-test-id="post-write-returnto"')
            ->assertSeeHtml('href="/usercp.php?action=mytorrents"');
    }

    public function test_edited_banner_omits_returnto_when_param_missing(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($owner, 'nexus-web')
            ->withQueryParams(['edited' => '1'])
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-banner-type="edited"')
            ->assertDontSeeHtml('data-test-id="post-write-returnto"');
    }

    public function test_existed_banner_renders_danger_variant(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($owner, 'nexus-web')
            ->withQueryParams(['existed' => '1'])
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-banner-type="existed"')
            ->assertSee('already been uploaded')
            ->assertSeeHtml('border-danger-200');
    }

    public function test_returnto_is_html_escaped_in_link_href(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        $hostile = '"><script>alert(1)</script>';

        Livewire::actingAs($owner, 'nexus-web')
            ->withQueryParams(['edited' => '1', 'returnto' => $hostile])
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSeeHtml('<script>alert(1)</script>')
            ->assertSeeHtml('href="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"');
    }

    public function test_edit_action_url_propagates_returnto(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($owner, 'nexus-web')
            ->withQueryParams(['edited' => '1', 'returnto' => '/usercp.php?action=mytorrents'])
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('/edit.php?id='.$torrentId.'&amp;returnto=%2Fusercp.php%3Faction%3Dmytorrents');
    }

    public function test_uploaded_takes_priority_over_edited_when_both_present(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($owner, 'nexus-web')
            ->withQueryParams(['uploaded' => '1', 'edited' => '1'])
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-banner-type="uploaded"')
            ->assertDontSeeHtml('data-banner-type="edited"');
    }

    private function createUser(): User
    {
        return $this->createLegacyUser(
            overrides: ['lang' => self::ENGLISH_LANGUAGE_ID],
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
}
