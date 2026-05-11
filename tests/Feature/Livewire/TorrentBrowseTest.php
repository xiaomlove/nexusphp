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
        // After the Strangler Fig flip, legacy=1 must survive the hop
        // so /torrents.php's guard sees it and stays on legacy.
        $this->assertStringContainsString('legacy=1', $location);
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

    public function test_clear_filters_also_resets_phase32_range_and_subcategory_filters(): void
    {
        $user = $this->createUser();

        $component = Livewire::actingAs($user, 'nexus-web')
            ->test(TorrentBrowse::class)
            ->set('sizeMin', 5)
            ->set('sizeMax', 50)
            ->set('seedersMin', 1)
            ->set('leechersMax', 100)
            ->set('snatchesMin', 0)
            ->set('source', 3)
            ->set('medium', 4)
            ->set('codec', 7)
            ->set('standard', 1)
            ->set('processing', 2)
            ->set('team', 8)
            ->set('audiocodec', 9)
            ->call('clearFilters');

        $this->assertNull($component->get('sizeMin'));
        $this->assertNull($component->get('sizeMax'));
        $this->assertNull($component->get('seedersMin'));
        $this->assertNull($component->get('leechersMax'));
        $this->assertNull($component->get('snatchesMin'));
        $this->assertSame(0, $component->get('source'));
        $this->assertSame(0, $component->get('medium'));
        $this->assertSame(0, $component->get('codec'));
        $this->assertSame(0, $component->get('standard'));
        $this->assertSame(0, $component->get('processing'));
        $this->assertSame(0, $component->get('team'));
        $this->assertSame(0, $component->get('audiocodec'));
    }

    public function test_size_min_excludes_torrents_smaller_than_threshold(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();
        $gb = 1024 ** 3;

        $smallId = $this->createTorrent($owner->id, ['size' => 500 * 1024 * 1024]);
        $largeId = $this->createTorrent($owner->id, ['size' => 4 * $gb]);

        $component = Livewire::actingAs($user, 'nexus-web')
            ->test(TorrentBrowse::class)
            ->set('sizeMin', 2);

        $ids = $component->viewData('torrents')->pluck('id')->all();
        $this->assertNotContains($smallId, $ids);
        $this->assertContains($largeId, $ids);
    }

    public function test_size_max_excludes_torrents_larger_than_threshold(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();
        $gb = 1024 ** 3;

        $smallId = $this->createTorrent($owner->id, ['size' => 1 * $gb]);
        $hugeId = $this->createTorrent($owner->id, ['size' => 50 * $gb]);

        $component = Livewire::actingAs($user, 'nexus-web')
            ->test(TorrentBrowse::class)
            ->set('sizeMax', 5);

        $ids = $component->viewData('torrents')->pluck('id')->all();
        $this->assertContains($smallId, $ids);
        $this->assertNotContains($hugeId, $ids);
    }

    public function test_seeders_min_filter_drops_low_seeder_torrents(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();
        $deadId = $this->createTorrent($owner->id, ['seeders' => 0]);
        $healthyId = $this->createTorrent($owner->id, ['seeders' => 25]);

        $component = Livewire::actingAs($user, 'nexus-web')
            ->test(TorrentBrowse::class)
            ->set('seedersMin', 5);

        $ids = $component->viewData('torrents')->pluck('id')->all();
        $this->assertNotContains($deadId, $ids);
        $this->assertContains($healthyId, $ids);
    }

    public function test_leechers_max_filter_caps_torrents_with_too_many_leechers(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();
        $quietId = $this->createTorrent($owner->id, ['leechers' => 1]);
        $busyId = $this->createTorrent($owner->id, ['leechers' => 200]);

        $component = Livewire::actingAs($user, 'nexus-web')
            ->test(TorrentBrowse::class)
            ->set('leechersMax', 10);

        $ids = $component->viewData('torrents')->pluck('id')->all();
        $this->assertContains($quietId, $ids);
        $this->assertNotContains($busyId, $ids);
    }

    public function test_snatches_range_filters_by_times_completed_column(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();
        $unsnatchedId = $this->createTorrent($owner->id, ['times_completed' => 0]);
        $popularId = $this->createTorrent($owner->id, ['times_completed' => 500]);
        $hugeId = $this->createTorrent($owner->id, ['times_completed' => 5000]);

        $component = Livewire::actingAs($user, 'nexus-web')
            ->test(TorrentBrowse::class)
            ->set('snatchesMin', 100)
            ->set('snatchesMax', 1000);

        $ids = $component->viewData('torrents')->pluck('id')->all();
        $this->assertNotContains($unsnatchedId, $ids);
        $this->assertContains($popularId, $ids);
        $this->assertNotContains($hugeId, $ids);
    }

    public function test_source_filter_keeps_only_matching_source(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();
        $matchedId = $this->createTorrent($owner->id, ['source' => 3]);
        $otherId = $this->createTorrent($owner->id, ['source' => 5]);

        $component = Livewire::actingAs($user, 'nexus-web')
            ->test(TorrentBrowse::class)
            ->set('source', 3);

        $ids = $component->viewData('torrents')->pluck('id')->all();
        $this->assertContains($matchedId, $ids);
        $this->assertNotContains($otherId, $ids);
    }

    public function test_codec_and_medium_filters_combine_with_and_semantics(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser();
        $matchedId = $this->createTorrent($owner->id, ['codec' => 2, 'medium' => 4]);
        $wrongCodecId = $this->createTorrent($owner->id, ['codec' => 99, 'medium' => 4]);
        $wrongMediumId = $this->createTorrent($owner->id, ['codec' => 2, 'medium' => 99]);

        $component = Livewire::actingAs($user, 'nexus-web')
            ->test(TorrentBrowse::class)
            ->set('codec', 2)
            ->set('medium', 4);

        $ids = $component->viewData('torrents')->pluck('id')->all();
        $this->assertContains($matchedId, $ids);
        $this->assertNotContains($wrongCodecId, $ids);
        $this->assertNotContains($wrongMediumId, $ids);
    }

    public function test_range_filter_hydrates_from_query_string_on_mount(): void
    {
        $user = $this->createUser();

        $component = Livewire::actingAs($user, 'nexus-web')
            ->withQueryParams([
                'size_begin' => '4',
                'seeders_end' => '0',
                'source' => '7',
            ])
            ->test(TorrentBrowse::class);

        $this->assertSame(4, $component->get('sizeMin'));
        $this->assertSame(0, $component->get('seedersMax'));
        $this->assertSame(7, $component->get('source'));
    }

    public function test_subcategory_options_are_mode_aware(): void
    {
        $user = $this->createUser();

        $globalSourceId = (int) NexusDB::table('sources')->insertGetId([
            'name' => 'Global-'.bin2hex(random_bytes(2)),
            'sort_index' => 1,
            'mode' => 0,
        ]);
        $sectionSourceId = (int) NexusDB::table('sources')->insertGetId([
            'name' => 'Section-'.bin2hex(random_bytes(2)),
            'sort_index' => 2,
            'mode' => 1,
        ]);
        $unrelatedSourceId = (int) NexusDB::table('sources')->insertGetId([
            'name' => 'Other-'.bin2hex(random_bytes(2)),
            'sort_index' => 3,
            'mode' => 2,
        ]);

        $modeAll = Livewire::actingAs($user, 'nexus-web')->test(TorrentBrowse::class);
        $allOptionsMap = $modeAll->instance()->subcategoryOptions();
        $allOptions = collect($allOptionsMap['source']['options'])->pluck('id')->all();
        $this->assertContains($globalSourceId, $allOptions);
        $this->assertContains($sectionSourceId, $allOptions);
        $this->assertContains($unrelatedSourceId, $allOptions);

        $mode1 = Livewire::actingAs($user, 'nexus-web')
            ->test(TorrentBrowse::class)
            ->set('mode', 1);
        $modeOneOptionsMap = $mode1->instance()->subcategoryOptions();
        $modeOneOptions = collect($modeOneOptionsMap['source']['options'])->pluck('id')->all();
        $this->assertContains($globalSourceId, $modeOneOptions);
        $this->assertContains($sectionSourceId, $modeOneOptions);
        $this->assertNotContains($unrelatedSourceId, $modeOneOptions);
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
