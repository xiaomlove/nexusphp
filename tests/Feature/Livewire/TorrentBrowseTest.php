<?php

namespace Tests\Feature\Livewire;

use App\Livewire\TorrentBrowse;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the Phase 3 spike contract for `App\Livewire\TorrentBrowse`.
 *
 * The component now mirrors more of `public/torrents.php`'s filter
 * surface — `spstate`, `incldead`, `inclbookmarked`, `tag_id`, plus
 * `name_asc` / `name_desc` / `comments` sorts. This file verifies
 * each filter narrows the result set in the way the legacy page
 * does, and pins the `?legacy=1` canary that lets the next Phase 3
 * PR flip URLs over with a quick rollback path.
 */
class TorrentBrowseTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    private int $categoryId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // The Livewire test harness still goes through the real
        // middleware stack including `LogUserIp`, which reads
        // `$_SERVER['REQUEST_URI']` directly.
        $_SERVER['REQUEST_URI'] = '/browse';

        $this->categoryId = (int) NexusDB::table('categories')->insertGetId([
            'mode' => 0,
            'class_name' => 'c_test',
            'name' => 'TestCat-'.bin2hex(random_bytes(2)),
            'image' => '',
            'sort_index' => 0,
            'icon_id' => 0,
        ]);
    }

    public function test_legacy_canary_redirects_to_torrents_php(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/browse?legacy=1&sort=newest');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/torrents.php', $location);
        $this->assertStringContainsString('sort=newest', $location);
        $this->assertStringNotContainsString('legacy=', $location);
    }

    public function test_spstate_free_filter_keeps_only_free_torrents(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();

        $freeId = $this->createTorrent($owner->id, [
            'sp_state' => Torrent::PROMOTION_FREE,
            'name' => 'free-torrent',
        ]);
        $normalId = $this->createTorrent($owner->id, [
            'sp_state' => Torrent::PROMOTION_NORMAL,
            'name' => 'normal-torrent',
        ]);

        $component = Livewire::actingAs($user, 'nexus-web')
            ->test(TorrentBrowse::class)
            ->set('spState', (string) Torrent::PROMOTION_FREE);

        $torrents = $component->viewData('torrents');
        $ids = $torrents->pluck('id')->all();

        $this->assertContains($freeId, $ids);
        $this->assertNotContains($normalId, $ids);
    }

    public function test_legacy_free_url_param_translates_to_spstate(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();
        $freeId = $this->createTorrent($owner->id, [
            'sp_state' => Torrent::PROMOTION_FREE,
        ]);
        $normalId = $this->createTorrent($owner->id, [
            'sp_state' => Torrent::PROMOTION_NORMAL,
        ]);

        $component = Livewire::actingAs($user, 'nexus-web')
            ->withQueryParams(['free' => true])
            ->test(TorrentBrowse::class);

        // `onlyFree` should be normalised to false after mount,
        // and `spState` should be promoted to "2" (FREE).
        $this->assertFalse($component->get('onlyFree'));
        $this->assertSame((string) Torrent::PROMOTION_FREE, $component->get('spState'));

        $ids = $component->viewData('torrents')->pluck('id')->all();
        $this->assertContains($freeId, $ids);
        $this->assertNotContains($normalId, $ids);
    }

    public function test_includedead_active_hides_invisible_torrents(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();
        $aliveId = $this->createTorrent($owner->id, ['visible' => Torrent::VISIBLE_YES]);
        $deadId = $this->createTorrent($owner->id, ['visible' => Torrent::VISIBLE_NO]);

        // Default (`active`) — dead row hidden.
        $component = Livewire::actingAs($user, 'nexus-web')
            ->test(TorrentBrowse::class);

        $ids = $component->viewData('torrents')->pluck('id')->all();
        $this->assertContains($aliveId, $ids);
        $this->assertNotContains($deadId, $ids);

        // Switch to `dead` — only invisible torrents.
        $component->set('includeDead', TorrentBrowse::INCLUDE_DEAD_DEAD);
        $ids = $component->viewData('torrents')->pluck('id')->all();
        $this->assertNotContains($aliveId, $ids);
        $this->assertContains($deadId, $ids);

        // Switch to `all` — both rows visible.
        $component->set('includeDead', TorrentBrowse::INCLUDE_DEAD_ALL);
        $ids = $component->viewData('torrents')->pluck('id')->all();
        $this->assertContains($aliveId, $ids);
        $this->assertContains($deadId, $ids);
    }

    public function test_inclbookmarked_only_filters_to_user_bookmarks(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();
        $bookmarkedId = $this->createTorrent($owner->id);
        $unbookmarkedId = $this->createTorrent($owner->id);

        NexusDB::table('bookmarks')->insert([
            'userid' => $user->id,
            'torrentid' => $bookmarkedId,
        ]);

        $component = Livewire::actingAs($user, 'nexus-web')
            ->test(TorrentBrowse::class)
            ->set('bookmarked', TorrentBrowse::BOOKMARK_ONLY);

        $ids = $component->viewData('torrents')->pluck('id')->all();
        $this->assertContains($bookmarkedId, $ids);
        $this->assertNotContains($unbookmarkedId, $ids);
    }

    public function test_inclbookmarked_exclude_hides_user_bookmarks(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();
        $bookmarkedId = $this->createTorrent($owner->id);
        $unbookmarkedId = $this->createTorrent($owner->id);

        NexusDB::table('bookmarks')->insert([
            'userid' => $user->id,
            'torrentid' => $bookmarkedId,
        ]);

        $component = Livewire::actingAs($user, 'nexus-web')
            ->test(TorrentBrowse::class)
            ->set('bookmarked', TorrentBrowse::BOOKMARK_EXCLUDE);

        $ids = $component->viewData('torrents')->pluck('id')->all();
        $this->assertNotContains($bookmarkedId, $ids);
        $this->assertContains($unbookmarkedId, $ids);
    }

    public function test_inclbookmarked_silently_resets_for_guest(): void
    {
        $component = Livewire::test(TorrentBrowse::class, ['bookmarked' => TorrentBrowse::BOOKMARK_ONLY]);

        // Guest can't have bookmarks, so the filter is reset on
        // mount() to avoid leaking another user's bookmark state.
        $this->assertSame(TorrentBrowse::BOOKMARK_ALL, $component->get('bookmarked'));
    }

    public function test_tag_filter_keeps_only_tagged_torrents(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();

        $tagId = (int) NexusDB::table('tags')->insertGetId([
            'name' => 'phase3-spike-'.bin2hex(random_bytes(2)),
            'priority' => 0,
            'mode' => 0,
        ]);

        $taggedId = $this->createTorrent($owner->id);
        $untaggedId = $this->createTorrent($owner->id);

        NexusDB::table('torrent_tags')->insert([
            'torrent_id' => $taggedId,
            'tag_id' => $tagId,
        ]);

        $component = Livewire::actingAs($user, 'nexus-web')
            ->test(TorrentBrowse::class)
            ->set('tagId', $tagId);

        $ids = $component->viewData('torrents')->pluck('id')->all();
        $this->assertContains($taggedId, $ids);
        $this->assertNotContains($untaggedId, $ids);
    }

    public function test_name_sort_orders_torrents_alphabetically(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();
        $zebraId = $this->createTorrent($owner->id, ['name' => 'zebra-'.bin2hex(random_bytes(2))]);
        $alphaId = $this->createTorrent($owner->id, ['name' => 'alpha-'.bin2hex(random_bytes(2))]);

        $component = Livewire::actingAs($user, 'nexus-web')
            ->test(TorrentBrowse::class)
            ->set('sort', 'name_asc');

        $ids = $component->viewData('torrents')->pluck('id')->all();
        $alphaPos = array_search($alphaId, $ids, true);
        $zebraPos = array_search($zebraId, $ids, true);

        $this->assertNotFalse($alphaPos, 'alpha torrent missing from result set');
        $this->assertNotFalse($zebraPos, 'zebra torrent missing from result set');
        $this->assertLessThan($zebraPos, $alphaPos, 'alpha must appear before zebra under name_asc sort');
    }

    public function test_comments_sort_uses_comments_column(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();
        $hotId = $this->createTorrent($owner->id, ['comments' => 50]);
        $coldId = $this->createTorrent($owner->id, ['comments' => 0]);

        $component = Livewire::actingAs($user, 'nexus-web')
            ->test(TorrentBrowse::class)
            ->set('sort', 'comments');

        $ids = $component->viewData('torrents')->pluck('id')->all();
        $hotPos = array_search($hotId, $ids, true);
        $coldPos = array_search($coldId, $ids, true);

        $this->assertNotFalse($hotPos);
        $this->assertNotFalse($coldPos);
        $this->assertLessThan($coldPos, $hotPos, 'hot torrent must appear before cold under comments sort');
    }

    public function test_clear_filters_resets_every_filter_to_default(): void
    {
        $user = $this->createUser();

        $component = Livewire::actingAs($user, 'nexus-web')
            ->test(TorrentBrowse::class)
            ->set('search', 'something')
            ->set('category', '1')
            ->set('sort', 'oldest')
            ->set('spState', (string) Torrent::PROMOTION_FREE)
            ->set('includeDead', TorrentBrowse::INCLUDE_DEAD_ALL)
            ->set('bookmarked', TorrentBrowse::BOOKMARK_ONLY)
            ->set('tagId', 42)
            ->call('clearFilters');

        $this->assertSame('', $component->get('search'));
        $this->assertSame('', $component->get('category'));
        $this->assertSame('newest', $component->get('sort'));
        $this->assertSame(TorrentBrowse::SPSTATE_ALL, $component->get('spState'));
        $this->assertSame(TorrentBrowse::INCLUDE_DEAD_ACTIVE, $component->get('includeDead'));
        $this->assertSame(TorrentBrowse::BOOKMARK_ALL, $component->get('bookmarked'));
        $this->assertSame(0, $component->get('tagId'));
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
            'name' => 'browse-test-'.bin2hex(random_bytes(4)),
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
        ], $overrides));
    }
}
