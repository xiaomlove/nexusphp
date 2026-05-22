<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/viewpeerlist.php` XHR contract.
 *
 * The legacy script was an XML AJAX endpoint called from
 * `public/js/common.js:44` (`viewpeerlist(torrentid)`); the response
 * body is `innerHTML`-injected into the toggle-able peer-list block
 * on `public/details.php`, so the wire shape (raw markup +
 * `text/xml` + no-cache headers) MUST stay stable.
 *
 * The route is intentionally NOT under `auth.nexus:nexus-web`
 * middleware: the legacy `if (isset($CURUSER))` gate becomes a
 * `LegacyContext::user() === null` check returning the empty-body
 * envelope, so guest XHRs see an empty body instead of a login
 * redirect (which would otherwise be `innerHTML`-spliced into the
 * details page).
 */
class ViewPeerListControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    /** @var list<int> */
    private array $createdPeerIds = [];

    /** @var list<int> */
    private array $createdTorrentIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/viewpeerlist.php';
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
    }

    protected function tearDown(): void
    {
        foreach ($this->createdPeerIds as $id) {
            NexusDB::table('peers')->where('id', $id)->delete();
        }
        foreach ($this->createdTorrentIds as $id) {
            NexusDB::table('torrents')->where('id', $id)->delete();
        }
        $this->createdPeerIds = [];
        $this->createdTorrentIds = [];

        parent::tearDown();
    }

    public function test_response_sets_xml_content_type_and_no_cache_headers(): void
    {
        $response = $this->get('/viewpeerlist.php?id=1');

        $response->assertOk();
        $this->assertSame(
            'text/xml; charset=utf-8',
            $response->headers->get('Content-Type'),
        );

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);

        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame(
            'Mon, 26 Jul 1997 05:00:00 GMT',
            $response->headers->get('Expires'),
        );
        $this->assertNotEmpty($response->headers->get('Last-Modified'));
    }

    public function test_guest_receives_empty_body(): void
    {
        $response = $this->get('/viewpeerlist.php?id=1');

        $response->assertOk();
        $this->assertSame('', (string) $response->getContent());
    }

    public function test_authed_user_with_zero_id_receives_empty_body(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/viewpeerlist.php');
        $response->assertOk();
        $this->assertSame('', (string) $response->getContent());

        $response = $this->get('/viewpeerlist.php?id=0');
        $response->assertOk();
        $this->assertSame('', (string) $response->getContent());
    }

    public function test_authed_user_with_unknown_torrent_receives_empty_body(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $maxId = (int) (NexusDB::table('torrents')->max('id') ?? 0);
        $bogus = $maxId + 9_999_999;

        $response = $this->get('/viewpeerlist.php?id='.$bogus);
        $response->assertOk();
        $this->assertSame('', (string) $response->getContent());
    }

    public function test_authed_user_with_no_peers_renders_zero_count_headings_only(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('debian-iso', $owner->id, ['seeders' => 0, 'leechers' => 0]);

        $body = (string) $this->get('/viewpeerlist.php?id='.$torrentId)->getContent();

        // Both blocks render their `<b>0 …</b>` heading…
        $this->assertMatchesRegularExpression('/<b>0\s+\S/', $body);
        // …but neither emits a `<table>` chrome (renderTable returns
        // early when the row list is empty).
        $this->assertStringNotContainsString('<table', $body);
        // The two headings always come back-to-back: seeder block
        // first, leecher block second.
        $this->assertSame(
            2,
            preg_match_all('/<b>0\s/', $body),
            'expected exactly two zero-count headings (seeders + leechers)',
        );
    }

    public function test_authed_user_renders_seeder_and_leecher_tables(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('Cool Release', $owner->id, [
            'size' => 1_073_741_824, // 1 GiB
            'seeders' => 0,
            'leechers' => 0,
        ]);

        // One seeder (finished, uploading), one leecher (still
        // downloading, halfway through).
        $seederUser = $this->createTestUser();
        $this->insertPeer($torrentId, $seederUser->id, [
            'seeder' => 'yes',
            'uploaded' => 2_147_483_648, // 2 GiB
            'downloaded' => 1_073_741_824,
            'to_go' => 0,
            'finishedat' => time() - 3600,
            'started' => Carbon::now()->subHours(2)->toDateTimeString(),
            'last_action' => Carbon::now()->subMinutes(5)->toDateTimeString(),
            'connectable' => 'yes',
            'agent' => 'qBittorrent/4.6.0',
        ]);

        $leecherUser = $this->createTestUser();
        $this->insertPeer($torrentId, $leecherUser->id, [
            'seeder' => 'no',
            'uploaded' => 134_217_728, // 128 MiB
            'downloaded' => 536_870_912, // 512 MiB
            'to_go' => 536_870_912,
            'finishedat' => 0,
            'started' => Carbon::now()->subMinutes(30)->toDateTimeString(),
            'last_action' => Carbon::now()->subMinutes(1)->toDateTimeString(),
            'connectable' => 'no',
            'agent' => 'Transmission/4.0.5',
        ]);

        $body = (string) $this->get('/viewpeerlist.php?id='.$torrentId)->getContent();

        // Seeder-table heading shows "1 …" (text comes from the
        // legacy lang_viewpeerlist dictionary).
        $this->assertMatchesRegularExpression('/<b>1\s+\S/', $body);

        // Two `<table>` chrome blocks, one per peer block.
        $this->assertSame(
            2,
            preg_match_all('/<table\s/', $body),
            'expected exactly two <table> blocks (seeders + leechers)',
        );

        // `get_username()` renders `<a href="userdetails.php?id=N">`
        // anchors for both peer rows.
        $this->assertStringContainsString('userdetails.php?id='.$seederUser->id, $body);
        $this->assertStringContainsString('userdetails.php?id='.$leecherUser->id, $body);

        // Connectable column reflects per-peer `connectable` enum.
        $this->assertStringContainsString('<font color=red>', $body);

        // Agent column is HTML-escaped output of `get_agent()`.
        $this->assertStringContainsString('qBittorrent', $body);
        $this->assertStringContainsString('Transmission', $body);

        // Complete % for the seeder is 100.00, for the leecher 50.00
        // (1 GiB total, 512 MiB to_go).
        $this->assertStringContainsString('100.00%', $body);
        $this->assertStringContainsString('50.00%', $body);
    }

    public function test_html_in_agent_column_is_escaped(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('xss-bait', $owner->id);

        $peerUser = $this->createTestUser();
        $this->insertPeer($torrentId, $peerUser->id, [
            'seeder' => 'yes',
            'uploaded' => 1024,
            'downloaded' => 1024,
            'to_go' => 0,
            'agent' => '<script>alert(1)</script>',
        ]);

        $body = (string) $this->get('/viewpeerlist.php?id='.$torrentId)->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function test_anonymous_torrent_owner_is_anonymized(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        // Owner uploads anonymously and is themselves the seeder.
        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('anonymized-release', $owner->id, [
            'anonymous' => 'yes',
            'size' => 1024,
        ]);
        $this->insertPeer($torrentId, $owner->id, [
            'seeder' => 'yes',
            'uploaded' => 4096,
            'downloaded' => 1024,
            'to_go' => 0,
            'agent' => 'qBittorrent/4.6.0',
        ]);

        $body = (string) $this->get('/viewpeerlist.php?id='.$torrentId)->getContent();

        // `<i>Anonymous</i>` (or per-locale equivalent) wraps the
        // username column for anonymized peers, instead of the
        // `<a href="userdetails.php?id=N">` link that `get_username`
        // would render.
        $this->assertStringContainsString('<i>', $body);
        // Viewer is not the owner and not a sysop, so they cannot
        // see the cleartext username for the anonymous peer.
        $this->assertStringNotContainsString(
            'userdetails.php?id='.$owner->id,
            $body,
        );
    }

    public function test_seeder_leecher_counts_are_reconciled_when_drift_detected(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $owner = $this->createTestUser();
        // Seed the cached counts at *wrong* values — endpoint must
        // detect the drift and rewrite them to match the actual
        // peer rows it just fetched.
        $torrentId = $this->insertTorrent('drift', $owner->id, [
            'size' => 1024,
            'seeders' => 99,
            'leechers' => 99,
        ]);

        $seederUser = $this->createTestUser();
        $this->insertPeer($torrentId, $seederUser->id, [
            'seeder' => 'yes',
            'uploaded' => 1024,
            'downloaded' => 1024,
            'to_go' => 0,
            'agent' => 'qBittorrent/4.6.0',
        ]);

        $this->get('/viewpeerlist.php?id='.$torrentId)->assertOk();

        $row = (array) NexusDB::table('torrents')
            ->where('id', $torrentId)
            ->select(['seeders', 'leechers'])
            ->first();

        $this->assertSame(1, (int) $row['seeders']);
        $this->assertSame(0, (int) $row['leechers']);
    }

    public function test_seeder_leecher_counts_are_left_alone_when_already_correct(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('stable', $owner->id, [
            'size' => 1024,
            'seeders' => 1,
            'leechers' => 0,
        ]);

        $seederUser = $this->createTestUser();
        $this->insertPeer($torrentId, $seederUser->id, [
            'seeder' => 'yes',
            'uploaded' => 1024,
            'downloaded' => 1024,
            'to_go' => 0,
            'agent' => 'qBittorrent/4.6.0',
        ]);

        $this->get('/viewpeerlist.php?id='.$torrentId)->assertOk();

        $row = (array) NexusDB::table('torrents')
            ->where('id', $torrentId)
            ->select(['seeders', 'leechers'])
            ->first();

        $this->assertSame(1, (int) $row['seeders']);
        $this->assertSame(0, (int) $row['leechers']);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(['lang' => self::ENGLISH_LANGUAGE_ID], $overrides),
        );
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function insertTorrent(string $name, int $ownerId, array $overrides = []): int
    {
        $id = (int) NexusDB::table('torrents')->insertGetId(array_merge([
            'name' => $name,
            'filename' => $name.'.torrent',
            'owner' => $ownerId,
            'info_hash' => hex2bin(str_pad(bin2hex(random_bytes(10)), 40, '0')),
            'anonymous' => 'no',
            'size' => 0,
            'seeders' => 0,
            'leechers' => 0,
            'added' => Carbon::now()->toDateTimeString(),
        ], $overrides));
        $this->createdTorrentIds[] = $id;

        return $id;
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function insertPeer(int $torrentId, int $userId, array $overrides = []): int
    {
        $id = (int) NexusDB::table('peers')->insertGetId(array_merge([
            'torrent' => $torrentId,
            'userid' => $userId,
            'peer_id' => random_bytes(20),
            'ip' => '203.0.113.1',
            'ipv4' => '203.0.113.1',
            'ipv6' => '',
            'port' => 51413,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 0,
            'seeder' => 'no',
            'connectable' => 'yes',
            'agent' => 'phpunit',
            'finishedat' => 0,
            'downloadoffset' => 0,
            'uploadoffset' => 0,
            'passkey' => bin2hex(random_bytes(16)),
            'is_seed_box' => 0,
            'started' => Carbon::now()->subMinutes(30)->toDateTimeString(),
            'last_action' => Carbon::now()->subMinutes(1)->toDateTimeString(),
        ], $overrides));
        $this->createdPeerIds[] = $id;

        return $id;
    }
}
