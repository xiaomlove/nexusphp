<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/retriver.php` contract.
 *
 * "Refresh external info" endpoint linked from
 * `public/details.php:449,473` (legacy IMDb cache refresh,
 * `?siteid=1`) and `nexus/PTGen/PTGen.php:143` (PTGen ratings,
 * `?siteid=imdb|douban|bangumi`). Authed; `updateextinfo`
 * permission required (default class 7 — Extreme User).
 *
 * Happy-path tests use `User::CLASS_STAFF_LEADER` so `user_can()`
 * short-circuits to `true` regardless of the seeded `authority`
 * settings — same pattern as `DelAcctAdminControllerTest`.
 */
class RetriverControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/retriver.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/retriver.php?id=1&type=1&siteid=1');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_user_below_updateextinfo_class_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/retriver.php?id=1&type=1&siteid=1')->assertForbidden();
    }

    public function test_missing_id_returns_404(): void
    {
        $user = $this->createAuthorizedUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/retriver.php?type=1&siteid=1')->assertNotFound();
        $this->get('/retriver.php?id=0&type=1&siteid=1')->assertNotFound();
    }

    public function test_missing_type_returns_404(): void
    {
        $user = $this->createAuthorizedUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/retriver.php?id=1&siteid=1')->assertNotFound();
        $this->get('/retriver.php?id=1&type=0&siteid=1')->assertNotFound();
    }

    public function test_missing_siteid_returns_404(): void
    {
        $user = $this->createAuthorizedUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/retriver.php?id=1&type=1')->assertNotFound();
        $this->get('/retriver.php?id=1&type=1&siteid=')->assertNotFound();
        $this->get('/retriver.php?id=1&type=1&siteid=0')->assertNotFound();
    }

    public function test_unknown_torrent_id_returns_404(): void
    {
        $user = $this->createAuthorizedUser();
        $this->actingAs($user, 'nexus-web');

        $maxId = (int) (NexusDB::table('torrents')->max('id') ?? 0);
        $bogus = $maxId + 9_999_999;

        $this->get('/retriver.php?id='.$bogus.'&type=1&siteid=1')
            ->assertNotFound();
    }

    public function test_unknown_siteid_returns_422(): void
    {
        $user = $this->createAuthorizedUser();
        $this->actingAs($user, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('unknown-site', $owner->id);

        try {
            $this->get('/retriver.php?id='.$torrentId.'&type=1&siteid=steam')
                ->assertStatus(422);
        } finally {
            NexusDB::table('torrents')->where('id', $torrentId)->delete();
        }
    }

    public function test_imdb_legacy_path_redirects_to_details(): void
    {
        $user = $this->createAuthorizedUser();
        $this->actingAs($user, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('imdb-fixture', $owner->id, [
            'url' => '',
        ]);

        try {
            $this->get('/retriver.php?id='.$torrentId.'&type=1&siteid=1')
                ->assertRedirect('/details.php?id='.$torrentId);
        } finally {
            NexusDB::table('torrents')->where('id', $torrentId)->delete();
        }
    }

    public function test_ptgen_imdb_path_redirects_to_details(): void
    {
        $this->assertPtGenSiteRedirects('imdb');
    }

    public function test_ptgen_douban_path_redirects_to_details(): void
    {
        $this->assertPtGenSiteRedirects('douban');
    }

    public function test_ptgen_bangumi_path_redirects_to_details(): void
    {
        $this->assertPtGenSiteRedirects('bangumi');
    }

    private function assertPtGenSiteRedirects(string $site): void
    {
        $user = $this->createAuthorizedUser();
        $this->actingAs($user, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('ptgen-'.$site, $owner->id);
        $this->insertTorrentExtra($torrentId);

        try {
            $this->get('/retriver.php?id='.$torrentId.'&type=1&siteid='.$site)
                ->assertRedirect('/details.php?id='.$torrentId);
        } finally {
            NexusDB::table('torrent_extras')->where('torrent_id', $torrentId)->delete();
            NexusDB::table('torrents')->where('id', $torrentId)->delete();
        }
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function insertTorrent(string $name, int $ownerId, array $overrides = []): int
    {
        return (int) NexusDB::table('torrents')->insertGetId(array_merge([
            'name' => $name,
            'filename' => $name.'.torrent',
            'owner' => $ownerId,
            'info_hash' => hex2bin(str_pad(bin2hex(random_bytes(10)), 40, '0')),
            'anonymous' => 'no',
            'added' => Carbon::now()->toDateTimeString(),
        ], $overrides));
    }

    private function insertTorrentExtra(int $torrentId): void
    {
        $now = Carbon::now()->toDateTimeString();
        NexusDB::table('torrent_extras')->insert([
            'torrent_id' => $torrentId,
            'descr' => '',
            'media_info' => null,
            'nfo' => null,
            'pt_gen' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createAuthorizedUser(): User
    {
        return $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
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
