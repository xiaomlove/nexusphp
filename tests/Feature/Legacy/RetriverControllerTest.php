<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/retriver.php?id=<id>&type=<n>&siteid=<site>` contract.
 *
 * The legacy script refreshes external metadata (IMDb / Douban /
 * Bangumi) for a torrent. Permission-gated by `updateextinfo`
 * (default: class 7 / Extreme User, configurable via
 * `$AUTHORITY['updateextinfo']` in `config/allconfig.php`).
 *
 * The endpoint is GET-only (linked from `public/details.php` as
 * `<a href="retriver.php?id=...&type=...&siteid=...">`) and redirects
 * to `/details.php?id={id}` on success, or returns an empty 200 on
 * validation failure / missing torrent (matching the legacy `exit()`
 * behaviour).
 */
class RetriverControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/retriver.php';
    }

    // ------------------------------------------------------------------
    // Auth / permission gate
    // ------------------------------------------------------------------

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/retriver.php?id=1&type=1&siteid=1');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_below_threshold_user_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/retriver.php?id=1&type=1&siteid=1')->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Validation — missing/invalid params → empty 200
    // ------------------------------------------------------------------

    public function test_missing_id_returns_empty_200(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_EXTREME_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/retriver.php?type=1&siteid=1');

        $response->assertOk();
        $this->assertEmpty($response->getContent());
    }

    public function test_missing_type_returns_empty_200(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_EXTREME_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/retriver.php?id=1&siteid=1');

        $response->assertOk();
        $this->assertEmpty($response->getContent());
    }

    public function test_missing_siteid_returns_empty_200(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_EXTREME_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/retriver.php?id=1&type=1');

        $response->assertOk();
        $this->assertEmpty($response->getContent());
    }

    public function test_zero_id_returns_empty_200(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_EXTREME_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/retriver.php?id=0&type=1&siteid=1');

        $response->assertOk();
        $this->assertEmpty($response->getContent());
    }

    // ------------------------------------------------------------------
    // Missing torrent → empty 200
    // ------------------------------------------------------------------

    public function test_nonexistent_torrent_returns_empty_200(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_EXTREME_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/retriver.php?id=9999999&type=1&siteid=1');

        $response->assertOk();
        $this->assertEmpty($response->getContent());
    }

    // ------------------------------------------------------------------
    // Unknown siteid → "Error!" text
    // ------------------------------------------------------------------

    public function test_unknown_siteid_returns_error_text(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_EXTREME_USER]);
        $this->actingAs($user, 'nexus-web');

        $torrentId = $this->createTorrent($user->id);

        $response = $this->get("/retriver.php?id={$torrentId}&type=1&siteid=unknown");

        $response->assertOk();
        $this->assertSame('Error!', $response->getContent());
    }

    // ------------------------------------------------------------------
    // siteid=1 (legacy IMDb) — no valid IMDb ID in URL → empty 200
    // ------------------------------------------------------------------

    public function test_siteid_1_without_imdb_url_returns_empty_200(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_EXTREME_USER]);
        $this->actingAs($user, 'nexus-web');

        // Create torrent with a URL that does NOT contain an IMDb ID.
        $torrentId = $this->createTorrent($user->id, url: 'https://example.com/no-imdb');

        $response = $this->get("/retriver.php?id={$torrentId}&type=1&siteid=1");

        $response->assertOk();
        $this->assertEmpty($response->getContent());
    }

    // ------------------------------------------------------------------
    // Helper methods
    // ------------------------------------------------------------------

    /**
     * Insert a minimal torrent row. Returns the new `torrents.id`.
     */
    private function createTorrent(int $ownerId, string $url = ''): int
    {
        return (int) NexusDB::table('torrents')->insertGetId([
            'name' => 'retriver-test-'.bin2hex(random_bytes(4)),
            'filename' => 'fixture.torrent',
            'save_as' => 'fixture',
            'cover' => '',
            'small_descr' => '',
            'url' => $url,
            'owner' => $ownerId,
            'added' => Carbon::now()->toDateTimeString(),
            'pieces_hash' => str_repeat('0', 40),
            'seeders' => 0,
            'leechers' => 0,
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
