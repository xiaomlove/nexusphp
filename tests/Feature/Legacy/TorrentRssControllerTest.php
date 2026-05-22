<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/torrentrss.php` contract.
 *
 * Personal RSS feed of the latest torrents — see
 * `App\Http\Controllers\Legacy\TorrentRssController` for the wire
 * shape. Auth is via the legacy `?passkey=<32-hex>` URL token, NOT
 * the session cookie — RSS readers / torrent clients consume this
 * feed.
 */
class TorrentRssControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    /** @var list<int> */
    private array $createdTorrentIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/torrentrss.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->createdTorrentIds as $id) {
            NexusDB::table('torrents')->where('id', $id)->delete();
        }
        $this->createdTorrentIds = [];

        parent::tearDown();
    }

    public function test_request_without_passkey_returns_require_passkey_body(): void
    {
        $response = $this->get('/torrentrss.php');

        $response->assertOk();
        $this->assertSame('require passkey', (string) $response->getContent());
    }

    public function test_request_with_unknown_passkey_returns_invalid_passkey_body(): void
    {
        $response = $this->get('/torrentrss.php?passkey='.str_repeat('z', 32));

        $response->assertOk();
        $this->assertSame('invalid passkey', (string) $response->getContent());
    }

    public function test_request_with_disabled_user_passkey_returns_disabled_body(): void
    {
        $user = $this->createTestUser([
            'enabled' => 'no',
            'passkey' => bin2hex(random_bytes(16)),
        ]);

        $response = $this->get('/torrentrss.php?passkey='.$user->passkey);

        $response->assertOk();
        $body = (string) $response->getContent();
        // Legacy typo preserved bit-for-bit ("disabed" not "disabled").
        $this->assertStringContainsString('account', $body);
    }

    public function test_request_with_parked_user_passkey_returns_disabled_body(): void
    {
        $user = $this->createTestUser([
            'parked' => 'yes',
            'passkey' => bin2hex(random_bytes(16)),
        ]);

        $response = $this->get('/torrentrss.php?passkey='.$user->passkey);

        $response->assertOk();
        $this->assertStringContainsString('account', (string) $response->getContent());
    }

    public function test_valid_passkey_returns_xml_with_rss_envelope(): void
    {
        $user = $this->createTestUser([
            'passkey' => bin2hex(random_bytes(16)),
        ]);
        $owner = $this->createTestUser();
        $this->insertTorrent('rss-test-'.bin2hex(random_bytes(2)), (int) $owner->id);

        $response = $this->get('/torrentrss.php?passkey='.$user->passkey);

        $response->assertOk();
        $this->assertStringContainsString(
            'text/xml',
            (string) $response->headers->get('Content-Type'),
        );
        $body = (string) $response->getContent();
        // RSS 2.0 envelope.
        $this->assertStringContainsString('<rss version="2.0">', $body);
        $this->assertStringContainsString('<channel>', $body);
        $this->assertStringContainsString('</channel>', $body);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge([
                'lang' => self::ENGLISH_LANGUAGE_ID,
                'class' => User::CLASS_USER,
            ], $overrides),
        );
    }

    private function insertTorrent(string $name, int $ownerId): int
    {
        $id = (int) NexusDB::table('torrents')->insertGetId([
            'name' => $name,
            'filename' => $name.'.torrent',
            'owner' => $ownerId,
            'info_hash' => hex2bin(str_pad(bin2hex(random_bytes(10)), 40, '0')),
            'anonymous' => 'no',
            'size' => 1024,
            'seeders' => 1,
            'leechers' => 0,
            'visible' => 'yes',
            'added' => Carbon::now()->toDateTimeString(),
        ]);
        $this->createdTorrentIds[] = $id;

        return $id;
    }
}
