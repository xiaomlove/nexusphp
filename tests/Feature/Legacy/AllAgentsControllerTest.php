<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/allagents.php` contract.
 *
 * The legacy script was a moderator-only listing of BitTorrent peer
 * agents and their connection counts. The migrated controller
 * preserves that contract: guests are redirected to login,
 * non-moderators get a real 403 (the legacy `stderr()` rendered HTTP
 * 200, which we deliberately tighten — same rationale as
 * `TakeUpdateController`), and moderators get the chrome-less
 * 2-column table (matching the `MoreSmiliesController` precedent —
 * the legacy `stdhead()` / `stdfoot()` envelope is not reproduced).
 */
class AllAgentsControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/allagents.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/allagents.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location')
        );
    }

    public function test_non_moderator_user_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/allagents.php');

        $response->assertForbidden();
    }

    public function test_moderator_sees_aggregated_peer_agents(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        // Pre-seed two peers from the same client and one from another
        // so the `GROUP BY agent, COUNT(*)` aggregation has something
        // to surface. `peers.torrent` is `int`; `userid` is `int`; the
        // exact column shape is what the legacy `SELECT agent,
        // COUNT(*) FROM peers GROUP BY agent ORDER BY agent` reads.
        $this->seedPeer('qBittorrent/4.6.4', userId: $user->id);
        $this->seedPeer('qBittorrent/4.6.4', userId: $user->id);
        $this->seedPeer('Transmission/4.0.5', userId: $user->id);

        $response = $this->get('/allagents.php');

        $response->assertOk();
        $body = (string) $response->getContent();

        // Page title + column headers from the legacy `<table>`.
        $this->assertStringContainsString('All Clients', $body);
        $this->assertStringContainsString('Client', $body);
        $this->assertStringContainsString('Counts', $body);

        // The two seeded agents must appear with the right counts. We
        // don't pin exact row ordering against the data set already in
        // the DB — `ORDER BY agent` is alphabetical, but other tests'
        // peers may be in the same transaction. Just assert that the
        // strings appear alongside their counts.
        $this->assertStringContainsString('qBittorrent/4.6.4', $body);
        $this->assertStringContainsString('Transmission/4.0.5', $body);
        $this->assertMatchesRegularExpression(
            '/qBittorrent\/4\.6\.4.*?2/s',
            $body,
            'Expected the qBittorrent agent row to surface count 2.'
        );
    }

    public function test_moderator_sees_safe_html_escaped_agent_string(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->seedPeer('<script>alert(1)</script>', userId: $user->id);

        $response = $this->get('/allagents.php');

        $response->assertOk();
        $body = (string) $response->getContent();

        // Raw `<script>` must not appear; the escaped form must.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    /**
     * Insert a row into `peers` that satisfies the NOT-NULL columns
     * the legacy schema enforces. Most columns have defaults or are
     * nullable; we set the ones the schema marks NOT-NULL without
     * defaults.
     */
    private function seedPeer(string $agent, int $userId): void
    {
        NexusDB::table('peers')->insert([
            'torrent' => 0,
            'peer_id' => bin2hex(random_bytes(10)),
            'userid' => $userId,
            'ip' => '127.0.0.1',
            'port' => 0,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 0,
            'started' => now()->toDateTimeString(),
            'last_action' => now()->toDateTimeString(),
            'seeder' => 'no',
            'agent' => $agent,
            'passkey' => bin2hex(random_bytes(16)),
            'connectable' => 'yes',
            'uploadoffset' => 0,
            'downloadoffset' => 0,
        ]);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(
                ['lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );
    }
}
