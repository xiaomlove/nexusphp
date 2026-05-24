<?php

namespace Tests\Feature\Livewire;

use App\Livewire\TorrentDetail;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class TorrentDetailCommentsPaginationTest extends FeatureTestCase
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

    public function test_pagination_defaults_to_last_page_when_cmtpage_unset(): void
    {
        $owner = $this->createUser();
        $author = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        $insertedIds = [];
        for ($i = 1; $i <= 25; $i++) {
            $insertedIds[] = $this->insertComment($torrentId, $author->id, [
                'text' => 'cmt-body-'.$i,
            ]);
        }
        $lastPageStart = $insertedIds[20];

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSee('Comments (25)', false)
            ->assertSee('cmt-body-21')
            ->assertSee('cmt-body-25')
            ->assertDontSee('cmt-body-1<')
            ->assertSee('data-page="3"', false)
            ->assertSeeHtml('data-test-id="comments-pager-current" data-page="3"');
    }

    public function test_pagination_renders_requested_page_when_cmtpage_param_set(): void
    {
        $owner = $this->createUser();
        $author = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        for ($i = 1; $i <= 25; $i++) {
            $this->insertComment($torrentId, $author->id, [
                'text' => 'paged-cmt-'.$i,
            ]);
        }

        Livewire::actingAs($owner, 'nexus-web')
            ->withQueryParams(['cmtpage' => 1])
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSee("paged-cmt-1\n", false)
            ->assertSee("paged-cmt-10\n", false)
            ->assertDontSee("paged-cmt-11\n", false)
            ->assertSeeHtml('data-test-id="comments-pager-current" data-page="1"');
    }

    public function test_pagination_clamps_out_of_range_cmtpage_to_last_page(): void
    {
        $owner = $this->createUser();
        $author = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        for ($i = 1; $i <= 12; $i++) {
            $this->insertComment($torrentId, $author->id, ['text' => 'clamp-cmt-'.$i]);
        }

        Livewire::actingAs($owner, 'nexus-web')
            ->withQueryParams(['cmtpage' => 99])
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-test-id="comments-pager-current" data-page="2"')
            ->assertSee('clamp-cmt-11')
            ->assertSee('clamp-cmt-12');
    }

    public function test_pager_is_hidden_when_only_one_page(): void
    {
        $owner = $this->createUser();
        $author = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        for ($i = 1; $i <= 5; $i++) {
            $this->insertComment($torrentId, $author->id, ['text' => 'single-cmt-'.$i]);
        }

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSee('data-test-id="comments-pager-top"', false)
            ->assertDontSee('data-test-id="comments-pager-bottom"', false);
    }

    public function test_showcomment_pref_hides_listing_but_keeps_reply_form(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);
        NexusDB::table('users')->where('id', $owner->id)->update(['showcomment' => 'no']);

        $author = $this->createUser();
        $this->insertComment($torrentId, $author->id, ['text' => 'hidden-cmt-body']);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSee('data-test-id="comments-listing-hidden"', false)
            ->assertDontSee('hidden-cmt-body')
            ->assertSee('data-test-id="comment-reply"', false);
    }

    public function test_avatars_pref_yes_renders_avatar_img(): void
    {
        $owner = $this->createUser();
        NexusDB::table('users')->where('id', $owner->id)->update(['avatars' => 'yes']);
        $torrentId = $this->createTorrent($owner->id);

        $author = $this->createUser();
        NexusDB::table('users')->where('id', $author->id)->update([
            'avatar' => 'https://example.com/avatars/alice.png',
        ]);
        $this->insertComment($torrentId, $author->id, ['text' => 'avatar-cmt']);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSee('data-test-id="comment-avatar"', false)
            ->assertSee('https://example.com/avatars/alice.png', false);
    }

    public function test_avatars_pref_no_does_not_render_avatar_img(): void
    {
        $owner = $this->createUser();
        NexusDB::table('users')->where('id', $owner->id)->update(['avatars' => 'no']);
        $torrentId = $this->createTorrent($owner->id);

        $author = $this->createUser();
        NexusDB::table('users')->where('id', $author->id)->update([
            'avatar' => 'https://example.com/avatars/bob.png',
        ]);
        $this->insertComment($torrentId, $author->id, ['text' => 'no-avatar-cmt']);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSee('data-test-id="comment-avatar"', false)
            ->assertDontSee('https://example.com/avatars/bob.png', false);
    }

    public function test_avatar_falls_back_to_default_when_user_has_no_avatar(): void
    {
        $owner = $this->createUser();
        NexusDB::table('users')->where('id', $owner->id)->update(['avatars' => 'yes']);
        $torrentId = $this->createTorrent($owner->id);

        $author = $this->createUser();
        NexusDB::table('users')->where('id', $author->id)->update(['avatar' => '']);
        $this->insertComment($torrentId, $author->id, ['text' => 'default-avatar-cmt']);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSee('/pic/default_avatar.png', false)
            ->assertSeeHtml('data-fallback="1"');
    }

    public function test_online_indicator_is_set_when_last_access_within_window(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        $recentAuthor = $this->createUser();
        NexusDB::table('users')->where('id', $recentAuthor->id)->update([
            'last_access' => Carbon::now()->subMinutes(5)->toDateTimeString(),
        ]);
        $this->insertComment($torrentId, $recentAuthor->id, ['text' => 'online-author-cmt']);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-test-id="comment-online" data-online="1"')
            ->assertSee('online-author-cmt');
    }

    public function test_online_indicator_is_unset_when_last_access_is_old(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        $staleAuthor = $this->createUser();
        NexusDB::table('users')->where('id', $staleAuthor->id)->update([
            'last_access' => Carbon::now()->subHours(2)->toDateTimeString(),
        ]);
        $this->insertComment($torrentId, $staleAuthor->id, ['text' => 'offline-author-cmt']);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-test-id="comment-online" data-online="0"')
            ->assertSee('offline-author-cmt');
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
            'name' => 'pager-test-'.bin2hex(random_bytes(4)),
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

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function insertComment(int $torrentId, int $userId, array $overrides = []): int
    {
        return (int) NexusDB::table('comments')->insertGetId(array_merge([
            'torrent' => $torrentId,
            'user' => $userId,
            'added' => Carbon::now()->toDateTimeString(),
            'text' => '',
            'ori_text' => '',
            'editedby' => 0,
            'editdate' => null,
            'offer' => 0,
            'request' => 0,
            'anonymous' => 'no',
        ], $overrides));
    }
}
