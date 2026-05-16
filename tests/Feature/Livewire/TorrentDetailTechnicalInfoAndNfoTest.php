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
 * Pins the Phase 3 — Modern UI A3 contract for the Technical info +
 * NFO viewer blocks on `App\Livewire\TorrentDetail` (PR-2 of the
 * second `details.php` Strangler-Fig wave).
 *
 * Technical info block (legacy: `details.php:314-340`):
 *
 *   - Hidden unless site setting `main.enable_technical_info = 'yes'`.
 *   - Hidden when `torrent_extras.media_info` is missing or empty.
 *   - For a typical MediaInfo payload, the rendered HTML is produced
 *     by `\Nexus\Torrent\TechnicalInformation::renderOnDetailsPage()`
 *     — we don't assert the exact rendering, only that the block is
 *     present and shows the parsed track-level data the legacy page
 *     surfaces.
 *
 * NFO viewer block (legacy: `details.php:350-356`):
 *
 *   - Hidden when the viewer lacks `viewnfo` permission.
 *   - Hidden when the viewer set `users.shownfo = 'no'`.
 *   - Hidden when `torrent_extras.nfo` is missing or empty
 *     (matches the legacy `nfosz > 0` guard).
 *   - Decoded through `App\Support\Codec::ibm437ToEntities()` — the
 *     same helper the legacy `code_new()` proxy delegates to.
 */
class TorrentDetailTechnicalInfoAndNfoTest extends FeatureTestCase
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

    public function test_technical_info_card_is_hidden_when_setting_disabled(): void
    {
        $this->seedSetting('main.enable_technical_info', 'no');

        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);
        $this->insertExtra($torrentId, [
            'media_info' => "General\nFormat : Matroska\n",
        ]);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSee('data-test-id="torrent-technical-info"', false);
    }

    public function test_technical_info_card_is_hidden_when_no_torrent_extras_row(): void
    {
        $this->seedSetting('main.enable_technical_info', 'yes');

        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);
        // No `torrent_extras` row inserted — matches LEFT JOIN
        // behaviour on the legacy details page.

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSee('data-test-id="torrent-technical-info"', false);
    }

    public function test_technical_info_card_is_hidden_when_media_info_is_empty(): void
    {
        $this->seedSetting('main.enable_technical_info', 'yes');

        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);
        $this->insertExtra($torrentId, ['media_info' => '']);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSee('data-test-id="torrent-technical-info"', false);
    }

    public function test_technical_info_card_renders_parsed_mediainfo(): void
    {
        $this->seedSetting('main.enable_technical_info', 'yes');

        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        // Realistic-shape MediaInfo blob — the parser splits on the
        // first-column-only line `General` / `Video` / `Audio` and
        // keys the rest by `Key : Value`.
        $mediaInfo = <<<'MEDIA'
            General
            Format                                   : Matroska
            File size                                : 4.20 GiB
            Duration                                 : 1 h 47 min

            Video
            Width                                    : 1920
            Height                                   : 1080
            Display aspect ratio                     : 16:9
            Bit rate                                 : 5 200 kb/s
            MEDIA;

        $this->insertExtra($torrentId, ['media_info' => $mediaInfo]);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSee('data-test-id="torrent-technical-info"', false)
            // Either the parser surfaces the structured key/value
            // pairs (preferred) or it falls back to the raw spoiler.
            // Both render the value strings somewhere in the markup.
            ->assertSee('Matroska');
    }

    public function test_technical_info_card_detects_bdinfo_format(): void
    {
        $this->seedSetting('main.enable_technical_info', 'yes');

        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        // BD-info dumps start with `DISC INFO` / `Disc Title` /
        // `Disc Label` — sniffing the first line triggers BdInfoExtra
        // instead of TechnicalInformation. We don't pin the exact
        // BD-info table here; only the dispatch.
        $bdInfo = "DISC INFO:\n\nDisc Title: Sample Movie\nDisc Label: SAMPLE_MOVIE\n";
        $this->insertExtra($torrentId, ['media_info' => $bdInfo]);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSee('data-test-id="torrent-technical-info"', false);
    }

    public function test_nfo_card_is_hidden_when_no_torrent_extras_row(): void
    {
        $this->seedSetting('main.enable_technical_info', 'no');

        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSee('data-test-id="torrent-nfo"', false);
    }

    public function test_nfo_card_is_hidden_when_nfo_blob_is_empty(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);
        $this->insertExtra($torrentId, ['nfo' => '']);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSee('data-test-id="torrent-nfo"', false);
    }

    public function test_nfo_card_is_hidden_when_viewer_opted_out(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser();

        NexusDB::table('users')
            ->where('id', $viewer->id)
            ->update(['shownfo' => 'no']);

        $torrentId = $this->createTorrent($owner->id);
        $this->insertExtra($torrentId, [
            'nfo' => "Plain ASCII NFO body\n",
        ]);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSee('data-test-id="torrent-nfo"', false);
    }

    public function test_nfo_card_renders_decoded_blob_for_authorised_viewer(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);
        $this->insertExtra($torrentId, [
            'nfo' => "Plain ASCII NFO body\nLine 2\n",
        ]);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSee('data-test-id="torrent-nfo"', false)
            ->assertSee('Plain ASCII NFO body')
            ->assertSee('Line 2');
    }

    public function test_nfo_card_escapes_special_characters(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);
        $this->insertExtra($torrentId, [
            'nfo' => '<script>alert(1)</script>',
        ]);

        $rendered = (string) Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->html();

        $this->assertStringContainsString('data-test-id="torrent-nfo"', $rendered);
        // The raw <script> tag must NOT survive — Codec::ibm437ToEntities
        // calls htmlspecialchars before mapping high bytes.
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
            'media_info' => '',
            'nfo' => '',
            'pt_gen' => '',
        ], $overrides));
    }

    /**
     * Write a single key into the `settings` table the same way the
     * Laravel admin UI writes them: a flat row with `autoload='yes'`
     * and dot-notation `name`. Inside `DatabaseTransactions` the row
     * rolls back on teardown.
     */
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
