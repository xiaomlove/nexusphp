<?php

namespace Tests\Feature\Livewire;

use App\Livewire\TorrentDetail;
use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the Phase 3 — Modern UI A3 contract for the
 * `App\Livewire\TorrentDetail` component at `/torrent/{id}`.
 *
 * The component is the Strangler-Fig replacement for
 * `public/details.php`. PR A focuses on bringing the legacy
 * permission matrix and the `approval_deny` ban-reason banner
 * up to parity with the legacy page. The covered branches are:
 *
 *   - missing torrent                                     → 404
 *   - `?legacy=1` canary                                  → 302 to legacy
 *   - `visible = 'no'` and viewer is not the owner        → 404
 *   - `banned  = 'yes'` and viewer is not the owner
 *       - regular user (no `seebanned`)                   → 403
 *       - staff leader (bypass via `CLASS_STAFF_LEADER`)  → 200
 *       - owner                                           → 200
 *   - `approval_status = APPROVAL_STATUS_DENY` with a
 *     matching `TorrentOperationLog::ACTION_TYPE_APPROVAL_DENY`
 *     row → ban-reason banner is rendered.
 *
 * The `can_access_torrent()` special-category gate is covered by
 * the legacy unit tests + e2e smoke against the upstream helper;
 * we exercise the access-control wiring here, not the upstream
 * helper itself.
 */
class TorrentDetailTest extends FeatureTestCase
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

    public function test_missing_torrent_returns_404(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/torrent/999999')->assertNotFound();
    }

    public function test_legacy_canary_redirects_to_details_php(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/torrent/'.$torrentId.'?legacy=1');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/details.php', $location);
        $this->assertStringContainsString('id='.$torrentId, $location);
        $this->assertStringContainsString('legacy=1', $location);
    }

    public function test_invisible_torrent_is_404_for_non_owner(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($owner->id, [
            'visible' => Torrent::VISIBLE_NO,
        ]);

        $this->actingAs($viewer, 'nexus-web');

        $this->get('/torrent/'.$torrentId)->assertNotFound();
    }

    public function test_invisible_torrent_is_visible_to_owner(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id, [
            'visible' => Torrent::VISIBLE_NO,
        ]);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSet('torrentId', $torrentId)
            ->assertSee('data-test-id="torrent-detail"', false);
    }

    public function test_banned_torrent_is_forbidden_for_non_owner_without_seebanned(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser(['class' => User::CLASS_USER]);
        $torrentId = $this->createTorrent($owner->id, [
            'banned' => Torrent::BANNED_YES,
        ]);

        $this->actingAs($viewer, 'nexus-web');

        $this->get('/torrent/'.$torrentId)->assertForbidden();
    }

    public function test_banned_torrent_is_visible_to_staff_leader(): void
    {
        $owner = $this->createUser();
        $staff = $this->createUser(['class' => User::CLASS_STAFF_LEADER]);
        $torrentId = $this->createTorrent($owner->id, [
            'banned' => Torrent::BANNED_YES,
        ]);

        Livewire::actingAs($staff, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSet('torrentId', $torrentId)
            ->assertSee('data-test-id="torrent-detail"', false);
    }

    public function test_banned_torrent_is_visible_to_owner(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id, [
            'banned' => Torrent::BANNED_YES,
        ]);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSet('torrentId', $torrentId)
            ->assertSee('data-test-id="torrent-detail"', false);
    }

    public function test_ban_reason_banner_is_rendered_when_approval_is_denied(): void
    {
        $owner = $this->createUser();
        $staff = $this->createUser(['class' => User::CLASS_STAFF_LEADER]);
        $torrentId = $this->createTorrent($owner->id, [
            'approval_status' => Torrent::APPROVAL_STATUS_DENY,
        ]);

        TorrentOperationLog::query()->create([
            'uid' => $staff->id,
            'torrent_id' => $torrentId,
            'action_type' => TorrentOperationLog::ACTION_TYPE_APPROVAL_DENY,
            'comment' => 'Wrong category, please re-upload.',
        ]);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSet('torrentId', $torrentId)
            ->assertSee('data-test-id="ban-reason"', false)
            ->assertSee('Wrong category, please re-upload.');
    }

    public function test_ban_reason_banner_is_absent_when_approval_status_is_normal(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id, [
            'approval_status' => Torrent::APPROVAL_STATUS_ALLOW,
        ]);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSet('torrentId', $torrentId)
            ->assertDontSee('data-test-id="ban-reason"', false);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge([
                'lang' => self::ENGLISH_LANGUAGE_ID,
            ], $overrides),
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
}
