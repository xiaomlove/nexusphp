<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/viewfilelist.php` XHR contract.
 *
 * The legacy script was an XML AJAX endpoint called from
 * `public/js/common.js:22` (`viewfilelist(torrentid)`); the
 * response body is `innerHTML`-injected into the toggle-able
 * file-list block on `public/details.php`, so the wire shape
 * (raw markup + `text/xml` + no-cache headers) MUST stay stable.
 *
 * The route is intentionally NOT under `auth.nexus:nexus-web`
 * middleware: the legacy `if (isset($CURUSER))` gate becomes a
 * `LegacyContext::user() === null` check returning the empty-body
 * envelope, so guest XHRs see an empty body instead of a login
 * redirect (which would otherwise be `innerHTML`-spliced into the
 * details page).
 */
class ViewFileListControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    /** @var array<int,int> */
    private array $createdFileRows = [];

    /** @var array<int,int> */
    private array $createdTorrentIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/viewfilelist.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFileRows as $id) {
            NexusDB::table('files')->where('id', $id)->delete();
        }
        foreach ($this->createdTorrentIds as $id) {
            NexusDB::table('torrents')->where('id', $id)->delete();
        }
        $this->createdFileRows = [];
        $this->createdTorrentIds = [];

        parent::tearDown();
    }

    public function test_response_sets_xml_content_type_and_no_cache_headers(): void
    {
        $response = $this->get('/viewfilelist.php?id=1');

        $response->assertOk();
        $this->assertSame(
            'text/xml; charset=utf-8',
            $response->headers->get('Content-Type'),
        );

        $cacheControl = $response->headers->get('Cache-Control');
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
        $response = $this->get('/viewfilelist.php?id=1');

        $response->assertOk();
        $this->assertSame('', (string) $response->getContent());
    }

    public function test_authed_user_with_zero_id_receives_empty_body(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/viewfilelist.php');
        $response->assertOk();
        $this->assertSame('', (string) $response->getContent());

        $response = $this->get('/viewfilelist.php?id=0');
        $response->assertOk();
        $this->assertSame('', (string) $response->getContent());
    }

    public function test_authed_user_with_no_files_renders_empty_table_chrome(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('debian-iso', $owner->id);

        $body = (string) $this->get('/viewfilelist.php?id='.$torrentId)->getContent();

        $this->assertStringContainsString('<style>', $body);
        $this->assertStringContainsString('.fileicon.fi-video', $body);
        $this->assertStringContainsString('<table class="main"', $body);
        $this->assertStringContainsString('<td class="colhead">Path</td>', $body);
        $this->assertStringContainsString('</table>', $body);
        $this->assertStringNotContainsString('<td class="rowfollow">', $body);
    }

    public function test_authed_user_renders_file_rows_with_badges_and_sizes(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('debian-iso', $owner->id);

        $this->insertFile($torrentId, 'movie.mkv', 1_073_741_824);
        $this->insertFile($torrentId, 'sample.mp3', 5_242_880);
        $this->insertFile($torrentId, 'README.txt', 1024);

        $body = (string) $this->get('/viewfilelist.php?id='.$torrentId)->getContent();

        $this->assertStringContainsString('class="fileicon fi-video"', $body);
        $this->assertStringContainsString('>MKV<', $body);
        $this->assertStringContainsString('movie.mkv', $body);

        $this->assertStringContainsString('class="fileicon fi-audio"', $body);
        $this->assertStringContainsString('>MP3<', $body);
        $this->assertStringContainsString('sample.mp3', $body);

        $this->assertStringContainsString('class="fileicon fi-text"', $body);
        $this->assertStringContainsString('>TXT<', $body);
        $this->assertStringContainsString('README.txt', $body);

        $this->assertStringContainsString('1.00 GB', $body);
        $this->assertStringContainsString('5.00 MB', $body);
        $this->assertStringContainsString('1.00 KB', $body);
    }

    public function test_unknown_extension_falls_back_to_other_badge(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('weird-iso', $owner->id);
        $this->insertFile($torrentId, 'data.xyz', 100);

        $body = (string) $this->get('/viewfilelist.php?id='.$torrentId)->getContent();

        $this->assertStringContainsString('class="fileicon fi-other"', $body);
        $this->assertStringContainsString('>XYZ<', $body);
    }

    public function test_filename_without_extension_uses_other_question_mark_badge(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('readme-only', $owner->id);
        $this->insertFile($torrentId, 'README', 50);

        $body = (string) $this->get('/viewfilelist.php?id='.$torrentId)->getContent();

        $this->assertStringContainsString('class="fileicon fi-other"', $body);
        $this->assertStringContainsString('>?<', $body);
    }

    public function test_html_in_filename_is_escaped(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('xss-test', $owner->id);
        $this->insertFile($torrentId, '<script>alert(1)</script>.mkv', 1024);

        $body = (string) $this->get('/viewfilelist.php?id='.$torrentId)->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>.mkv', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;.mkv', $body);
    }

    public function test_unknown_torrent_renders_empty_table_chrome(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $maxId = (int) (NexusDB::table('torrents')->max('id') ?? 0);
        $bogus = $maxId + 9_999_999;

        $body = (string) $this->get('/viewfilelist.php?id='.$bogus)->getContent();

        $this->assertStringContainsString('<table class="main"', $body);
        $this->assertStringNotContainsString('<td class="rowfollow">', $body);
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
            'added' => now()->toDateTimeString(),
        ], $overrides));
        $this->createdTorrentIds[] = $id;

        return $id;
    }

    private function insertFile(int $torrentId, string $filename, int $size): int
    {
        $id = (int) NexusDB::table('files')->insertGetId([
            'torrent' => $torrentId,
            'filename' => $filename,
            'size' => $size,
        ]);
        $this->createdFileRows[] = $id;

        return $id;
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
}
