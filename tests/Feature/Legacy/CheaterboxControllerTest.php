<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class CheaterboxControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/cheaterbox.php';
        NexusDB::table('cheaters')->delete();
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/cheaterbox.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_below_staff_is_forbidden(): void
    {
        $viewer = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($viewer, 'nexus-web');

        $this->get('/cheaterbox.php')->assertForbidden();
    }

    public function test_empty_table_renders_notice(): void
    {
        $viewer = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($viewer, 'nexus-web');

        $response = $this->get('/cheaterbox.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('No suspect detected.', $body);
    }

    public function test_listing_renders_row(): void
    {
        $viewer = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $cheater = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $torrentId = $this->insertTorrent('Sample Torrent');
        $this->insertCheater((int) $cheater->id, $torrentId, [
            'uploaded' => 8192,
            'downloaded' => 1024,
            'comment' => '<script>alert(1)</script>',
        ]);

        $response = $this->get('/cheaterbox.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Sample Torrent', $body);
        $this->assertStringContainsString($cheater->username, $body);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function test_setdealt_post_marks_rows(): void
    {
        $viewer = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $cheater = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $torrentId = $this->insertTorrent('T1');
        $cheaterId = $this->insertCheater((int) $cheater->id, $torrentId);

        $response = $this->post('/cheaterbox.php', [
            'setdealt' => 'Set dealt',
            'delcheater' => [$cheaterId],
        ]);

        $response->assertRedirect('/cheaterbox.php');
        $row = (array) NexusDB::table('cheaters')->where('id', $cheaterId)->first();
        $this->assertSame(1, (int) ($row['dealtwith'] ?? 0));
        $this->assertSame((int) $viewer->id, (int) ($row['dealtby'] ?? 0));
    }

    public function test_setdealt_without_selection_returns_422(): void
    {
        $viewer = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($viewer, 'nexus-web');

        $response = $this->post('/cheaterbox.php', ['setdealt' => 'Set dealt']);

        $response->assertStatus(422);
    }

    public function test_delete_post_removes_rows(): void
    {
        $viewer = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $cheater = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $torrentId = $this->insertTorrent('T2');
        $cheaterId = $this->insertCheater((int) $cheater->id, $torrentId);

        $response = $this->post('/cheaterbox.php', [
            'delete' => 'Delete',
            'delcheater' => [$cheaterId],
        ]);

        $response->assertRedirect('/cheaterbox.php');
        $this->assertSame(0, (int) NexusDB::table('cheaters')->where('id', $cheaterId)->count());
    }

    public function test_delete_without_selection_returns_422(): void
    {
        $viewer = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($viewer, 'nexus-web');

        $response = $this->post('/cheaterbox.php', ['delete' => 'Delete']);

        $response->assertStatus(422);
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
    private function insertCheater(int $userId, int $torrentId, array $overrides = []): int
    {
        return (int) NexusDB::table('cheaters')->insertGetId(array_merge([
            'userid' => $userId,
            'torrentid' => $torrentId,
            'hit' => 1,
            'uploaded' => 0,
            'downloaded' => 0,
            'anctime' => 60,
            'seeders' => 1,
            'leechers' => 0,
            'comment' => '',
            'dealtwith' => 0,
            'dealtby' => 0,
            'added' => date('Y-m-d H:i:s'),
        ], $overrides));
    }
}
