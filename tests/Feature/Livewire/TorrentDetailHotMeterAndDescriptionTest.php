<?php

namespace Tests\Feature\Livewire;

use App\Livewire\TorrentDetail;
use App\Models\Torrent;
use App\Models\User;
use App\Support\BbcodeRenderer;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins the Phase 3 — Modern UI A3 contract for the Hot meter +
 * full Description blocks on `App\Livewire\TorrentDetail`
 * (PR-1 of the second `details.php` Strangler-Fig wave).
 *
 * Covers:
 *
 *   - Hot meter card surfaces views / hits / snatched count / last
 *     seeder activity. The first three render through `number_format()`,
 *     last seeder uses Carbon's `diffForHumans()`.
 *   - Description card hydrates `torrent_extras.descr` through
 *     {@see BbcodeRenderer::toHtml()}.
 *       - Card is hidden when no `torrent_extras` row exists.
 *       - Card is hidden when `descr` is the empty string.
 *       - Card is hidden when the viewer set
 *         `users.showdescription = 'no'`.
 *       - Common BBCode tags (`[b]`, `[url]`) are rendered as HTML.
 *       - Raw HTML in `descr` is escaped, not passed through.
 */
class TorrentDetailHotMeterAndDescriptionTest extends FeatureTestCase
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

    public function test_hot_meter_renders_views_hits_snatched_last_seeder(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id, [
            'views' => 1234,
            'hits' => 56789,
            'times_completed' => 42,
            'last_action' => Carbon::now()->subHours(3)->toDateTimeString(),
        ]);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSee('data-test-id="hot-meter"', false)
            ->assertSee('data-test-id="hot-meter-views"', false)
            ->assertSee('1,234')
            ->assertSee('data-test-id="hot-meter-hits"', false)
            ->assertSee('56,789')
            ->assertSee('data-test-id="hot-meter-snatched"', false)
            ->assertSee('42')
            ->assertSee('data-test-id="hot-meter-last-seeder"', false)
            ->assertSee('3 hours ago');
    }

    public function test_hot_meter_last_seeder_falls_back_to_em_dash_when_unset(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id, [
            'last_action' => null,
        ]);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSee('data-test-id="hot-meter-last-seeder"', false)
            ->assertSee('—');
    }

    public function test_description_card_is_hidden_when_no_torrent_extras_row(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);
        // No `torrent_extras` row inserted — matches LEFT JOIN
        // behaviour on the legacy details page.

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSee('data-test-id="torrent-description"', false);
    }

    public function test_description_card_is_hidden_when_descr_is_empty_string(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);
        $this->insertExtra($torrentId, ['descr' => '']);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSee('data-test-id="torrent-description"', false);
    }

    public function test_description_card_is_hidden_when_viewer_opted_out(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser();

        NexusDB::table('users')
            ->where('id', $viewer->id)
            ->update(['showdescription' => 'no']);

        $torrentId = $this->createTorrent($owner->id);
        $this->insertExtra($torrentId, ['descr' => 'real description body']);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSee('data-test-id="torrent-description"', false)
            ->assertDontSee('real description body');
    }

    public function test_description_renders_bbcode_tags_as_html(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);
        $this->insertExtra($torrentId, [
            'descr' => '[b]Bold[/b] and [url=https://example.com/foo]link[/url]',
        ]);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSee('data-test-id="torrent-description"', false)
            ->assertSee('<strong>Bold</strong>', false)
            // BbcodeRenderer adds rel + target on anchors and only
            // allows http/https hrefs.
            ->assertSee('href="https://example.com/foo"', false)
            ->assertSee('>link</a>', false);
    }

    public function test_description_escapes_raw_html(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);
        $this->insertExtra($torrentId, [
            'descr' => '<script>alert(1)</script>',
        ]);

        $rendered = (string) Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->html();

        $this->assertStringContainsString('data-test-id="torrent-description"', $rendered);
        // The raw <script> tag must NOT be present anywhere in the
        // rendered HTML — BbcodeRenderer escapes it via htmlspecialchars.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $rendered);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $rendered);
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

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function insertExtra(int $torrentId, array $overrides = []): int
    {
        return (int) NexusDB::table('torrent_extras')->insertGetId(array_merge([
            'torrent_id' => $torrentId,
            'descr' => '',
            'ori_descr' => '',
            'media_info' => '',
            'nfo' => '',
            'pt_gen' => '',
        ], $overrides));
    }
}
