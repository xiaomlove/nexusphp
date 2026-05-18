<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class ViewNfoControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/viewnfo.php';
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';

        $this->setSetting('main.enablenfo', 'yes');
    }

    private function createTestUser(array $overrides = []): User
    {
        $parked = $overrides['parked'] ?? null;
        unset($overrides['parked']);

        $user = $this->createLegacyUser(
            overrides: array_merge(
                ['lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );

        if ($parked !== null) {
            NexusDB::table('users')->where('id', $user->id)->update(['parked' => $parked]);
            $user->refresh();
        }

        return $user;
    }

    private function setSetting(string $name, string $value): void
    {
        $now = Carbon::now();
        NexusDB::table('settings')->updateOrInsert(
            ['name' => $name],
            ['value' => $value, 'autoload' => 'yes', 'updated_at' => $now, 'created_at' => $now],
        );
    }

    private function createTorrent(int $ownerId, string $name = 'Test Torrent'): int
    {
        return (int) NexusDB::table('torrents')->insertGetId([
            'name' => $name,
            'filename' => 'test.torrent',
            'owner' => $ownerId,
            'info_hash' => hex2bin(str_pad(bin2hex(random_bytes(10)), 40, '0')),
            'anonymous' => 'no',
            'added' => Carbon::now()->toDateTimeString(),
        ]);
    }

    private function insertNfo(int $torrentId, string $nfo): void
    {
        NexusDB::table('torrent_extras')->insert([
            'torrent_id' => $torrentId,
            'nfo' => $nfo,
        ]);
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/viewnfo.php?id=1');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_parked_user_is_forbidden(): void
    {
        $user = $this->createTestUser([
            'class' => User::CLASS_STAFF_LEADER,
            'parked' => 'yes',
        ]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/viewnfo.php?id=1')->assertForbidden();
    }

    public function test_user_without_viewnfo_permission_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $owner = $this->createTestUser(['class' => User::CLASS_USER]);
        $torrentId = $this->createTorrent($owner->id);
        $this->insertNfo($torrentId, "Hello\n");

        $this->get('/viewnfo.php?id='.$torrentId)->assertForbidden();
    }

    public function test_invalid_id_is_forbidden(): void
    {
        $staff = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staff, 'nexus-web');

        $this->get('/viewnfo.php?id=0')->assertForbidden();
    }

    public function test_enablenfo_disabled_is_forbidden(): void
    {
        $this->setSetting('main.enablenfo', 'no');

        $staff = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staff, 'nexus-web');

        $owner = $this->createTestUser(['class' => User::CLASS_USER]);
        $torrentId = $this->createTorrent($owner->id);

        $this->get('/viewnfo.php?id='.$torrentId)->assertForbidden();
    }

    public function test_unknown_torrent_renders_puke_notice(): void
    {
        $staff = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staff, 'nexus-web');

        $response = $this->get('/viewnfo.php?id=987654321');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>View NFO File</title>', $body);
        $this->assertStringContainsString('Puke', $body);
    }

    public function test_valid_torrent_renders_nfo_and_view_links(): void
    {
        $staff = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staff, 'nexus-web');

        $owner = $this->createTestUser(['class' => User::CLASS_USER]);
        $torrentId = $this->createTorrent($owner->id, 'Cool Release v1.0');
        $this->insertNfo($torrentId, "Hello plain ASCII NFO\nLine 2\n");

        $response = $this->get('/viewnfo.php?id='.$torrentId);

        $response->assertOk();
        $body = (string) $response->getContent();

        $this->assertStringContainsString('<title>View NFO File</title>', $body);
        $this->assertStringContainsString('NFO for', $body);
        $this->assertStringContainsString('details.php?id='.$torrentId, $body);
        $this->assertStringContainsString('Cool Release v1.0', $body);
        $this->assertStringContainsString('viewnfo.php?id='.$torrentId.'&amp;view=magic', $body);
        $this->assertStringContainsString('viewnfo.php?id='.$torrentId.'&amp;view=latin-1', $body);
        $this->assertStringContainsString('Hello plain ASCII NFO', $body);
        $this->assertStringContainsString('Line 2', $body);
        $this->assertStringContainsString("font-family: 'Courier New', monospace", $body);
    }

    public function test_torrent_name_is_html_escaped(): void
    {
        $staff = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staff, 'nexus-web');

        $owner = $this->createTestUser(['class' => User::CLASS_USER]);
        $torrentId = $this->createTorrent($owner->id, '<script>alert(1)</script>');
        $this->insertNfo($torrentId, "x\n");

        $response = $this->get('/viewnfo.php?id='.$torrentId);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function test_fonthack_view_uses_ms_linedraw_font(): void
    {
        $staff = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staff, 'nexus-web');

        $owner = $this->createTestUser(['class' => User::CLASS_USER]);
        $torrentId = $this->createTorrent($owner->id);
        $this->insertNfo($torrentId, "fonthack\n");

        $response = $this->get('/viewnfo.php?id='.$torrentId.'&view=fonthack');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString("font-family: 'MS LineDraw', 'Terminal', monospace", $body);
    }

    public function test_unknown_view_param_falls_back_to_default(): void
    {
        $staff = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staff, 'nexus-web');

        $owner = $this->createTestUser(['class' => User::CLASS_USER]);
        $torrentId = $this->createTorrent($owner->id);
        $this->insertNfo($torrentId, "default\n");

        $response = $this->get('/viewnfo.php?id='.$torrentId.'&view=%22%3E%3Cscript%3Ealert(1)%3C/script%3E');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString("font-family: 'Courier New', monospace", $body);
    }

    public function test_high_byte_nfo_is_converted_to_html_entities(): void
    {
        $staff = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staff, 'nexus-web');

        $owner = $this->createTestUser(['class' => User::CLASS_USER]);
        $torrentId = $this->createTorrent($owner->id);
        $this->insertNfo($torrentId, "\xC9\xCD\xBB");

        $response = $this->get('/viewnfo.php?id='.$torrentId);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('&#9556;', $body);
        $this->assertStringContainsString('&#9552;', $body);
        $this->assertStringContainsString('&#9559;', $body);
    }
}
