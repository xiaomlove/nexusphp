<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class ViewSnatchesControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/viewsnatches.php';
        NexusDB::table('snatched')->delete();
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/viewsnatches.php?id=1');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_parked_user_is_forbidden(): void
    {
        $viewer = $this->createTestUser();
        NexusDB::table('users')->where('id', $viewer->id)->update(['parked' => 'yes']);
        $viewer->refresh();
        $this->actingAs($viewer, 'nexus-web');

        $this->get('/viewsnatches.php?id=1')->assertForbidden();
    }

    public function test_missing_id_is_rejected(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $this->get('/viewsnatches.php')->assertStatus(422);
    }

    public function test_invalid_id_is_rejected(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $this->get('/viewsnatches.php?id=0')->assertStatus(422);
    }

    public function test_no_snatches_renders_empty_notice(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $torrentId = $this->insertTorrent('My Torrent');

        $response = $this->get('/viewsnatches.php?id='.$torrentId);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>View Snatches</title>', $body);
        $this->assertStringContainsString('No snatched users found.', $body);
        $this->assertStringContainsString('details.php?id='.$torrentId, $body);
        $this->assertStringContainsString('My Torrent', $body);
    }

    public function test_snatch_renders_in_table(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $torrentId = $this->insertTorrent('Sample');
        $snatcher = $this->createTestUser(['username' => 'snatcher_'.bin2hex(random_bytes(3))]);
        $this->insertSnatch($torrentId, (int) $snatcher->id, [
            'uploaded' => 4096,
            'downloaded' => 2048,
            'ip' => '10.0.0.5',
        ]);

        $response = $this->get('/viewsnatches.php?id='.$torrentId);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString($snatcher->username, $body);
        $this->assertStringContainsString('Uploaded/Downloaded', $body);
        $this->assertStringContainsString('Users that have finished downloading this torrent.', $body);
    }

    public function test_ip_column_hidden_from_users_without_userprofile_permission(): void
    {
        $viewer = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($viewer, 'nexus-web');

        $torrentId = $this->insertTorrent('IP Test');
        $snatcher = $this->createTestUser();
        $this->insertSnatch($torrentId, (int) $snatcher->id, ['ip' => '203.0.113.10']);

        $body = (string) $this->get('/viewsnatches.php?id='.$torrentId)->getContent();
        $this->assertStringNotContainsString('203.0.113.10', $body);
    }

    public function test_ip_column_shown_to_moderator(): void
    {
        $viewer = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($viewer, 'nexus-web');

        $torrentId = $this->insertTorrent('IP Test 2');
        $snatcher = $this->createTestUser();
        $this->insertSnatch($torrentId, (int) $snatcher->id, ['ip' => '198.51.100.42']);

        $body = (string) $this->get('/viewsnatches.php?id='.$torrentId)->getContent();
        $this->assertStringContainsString('198.51.100.42', $body);
    }

    public function test_html_in_torrent_name_is_escaped(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $torrentId = $this->insertTorrent('<script>alert(1)</script>');

        $body = (string) $this->get('/viewsnatches.php?id='.$torrentId)->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function test_pagination_renders_when_over_page_size(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $torrentId = $this->insertTorrent('Many Snatches');
        for ($i = 0; $i < 30; $i++) {
            $snatcher = $this->createTestUser();
            $this->insertSnatch($torrentId, (int) $snatcher->id);
        }

        $body = (string) $this->get('/viewsnatches.php?id='.$torrentId)->getContent();
        $this->assertStringContainsString('Next', $body);
        $this->assertStringContainsString('viewsnatches.php?id='.$torrentId.'&amp;page=1', $body);

        $bodyPage2 = (string) $this->get('/viewsnatches.php?id='.$torrentId.'&page=1')->getContent();
        $this->assertStringContainsString('Prev', $bodyPage2);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(overrides: array_merge(
            ['lang' => self::ENGLISH_LANGUAGE_ID],
            $overrides,
        ));
    }

    private function insertTorrent(string $name): int
    {
        return (int) NexusDB::table('torrents')->insertGetId([
            'name' => $name,
            'filename' => 'sample.torrent',
            'info_hash' => bin2hex(random_bytes(10)),
            'category' => 1,
            'visible' => 'yes',
            'added' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function insertSnatch(int $torrentId, int $userId, array $overrides = []): void
    {
        $now = date('Y-m-d H:i:s');
        NexusDB::table('snatched')->insert(array_merge([
            'torrentid' => $torrentId,
            'userid' => $userId,
            'ip' => '127.0.0.1',
            'port' => 0,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 0,
            'seedtime' => 0,
            'leechtime' => 0,
            'last_action' => $now,
            'completedat' => $now,
            'finished' => 'yes',
        ], $overrides));
    }
}
