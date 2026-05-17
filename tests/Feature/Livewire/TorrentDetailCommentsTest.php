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

/**
 * Pins the Phase 3 — Modern UI A3 contract for the Comments listing
 * (read-only) on `App\Livewire\TorrentDetail` (PR E of the
 * `details.php` Strangler-Fig series).
 *
 * Covers:
 *
 *   - Comments card: empty state when no comments exist, populated
 *     state when there are. Ordering is oldest-first (legacy
 *     `ORDER BY id`).
 *   - BBCode → HTML rendering via `App\Support\BbcodeRenderer` —
 *     `[b]…[/b]` becomes `<strong>…</strong>`, unsupported / unknown
 *     content is escaped.
 *   - Edited badge surfaces only when `editdate` is set.
 *   - Anonymity (mirrors legacy `commenttable()`):
 *       * `comments.anonymous = 'yes'` hides the username from
 *         regular viewers,
 *       * `users.privacy = 'strong'` hides the username from regular
 *         viewers,
 *       * either rule is suspended for the comment's own author and
 *         for any viewer with the `viewanonymous` permission.
 */
class TorrentDetailCommentsTest extends FeatureTestCase
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

    public function test_comments_card_shows_empty_state_when_no_comments(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSee('data-test-id="comments-empty"', false)
            ->assertDontSee('data-test-id="comments-list"', false)
            ->assertSee('Comments (0)', false);
    }

    public function test_comments_render_in_id_ascending_order_with_bbcode(): void
    {
        $owner = $this->createUser();
        $author1 = $this->createUser();
        $author2 = $this->createUser();
        $otherTorrentOwner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);
        $otherTorrentId = $this->createTorrent($otherTorrentOwner->id);

        $first = $this->insertComment($torrentId, $author1->id, [
            'text' => 'first reply — [b]bold here[/b]',
            'added' => Carbon::now()->subHour()->toDateTimeString(),
        ]);
        $second = $this->insertComment($torrentId, $author2->id, [
            'text' => 'second reply',
            'added' => Carbon::now()->toDateTimeString(),
        ]);
        // A comment on a different torrent must not leak into this listing.
        $foreign = $this->insertComment($otherTorrentId, $author1->id, ['text' => 'unrelated']);

        try {
            $html = Livewire::actingAs($owner, 'nexus-web')
                ->test(TorrentDetail::class, ['id' => $torrentId])
                ->assertSee('Comments (2)', false)
                ->assertSee('data-comment-id="'.$first.'"', false)
                ->assertSee('data-comment-id="'.$second.'"', false)
                ->assertDontSee('data-comment-id="'.$foreign.'"', false)
                ->assertSee('<strong>bold here</strong>', false)
                ->html();

            $firstPos = strpos($html, 'data-comment-id="'.$first.'"');
            $secondPos = strpos($html, 'data-comment-id="'.$second.'"');

            $this->assertNotFalse($firstPos);
            $this->assertNotFalse($secondPos);
            $this->assertLessThan($secondPos, $firstPos, 'older comment should render before newer one');
        } finally {
            NexusDB::table('comments')->whereIn('id', [$first, $second, $foreign])->delete();
        }
    }

    public function test_edited_badge_only_appears_when_editdate_is_set(): void
    {
        $owner = $this->createUser();
        $author = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        $editedId = $this->insertComment($torrentId, $author->id, [
            'text' => 'edited',
            'editedby' => $author->id,
            'editdate' => Carbon::now()->toDateTimeString(),
        ]);
        $pristineId = $this->insertComment($torrentId, $author->id, ['text' => 'pristine']);

        try {
            $html = Livewire::actingAs($owner, 'nexus-web')
                ->test(TorrentDetail::class, ['id' => $torrentId])
                ->html();

            $editedSegment = $this->commentSegment($html, $editedId);
            $pristineSegment = $this->commentSegment($html, $pristineId);

            $this->assertStringContainsString('data-test-id="comment-edited"', $editedSegment);
            $this->assertStringNotContainsString('data-test-id="comment-edited"', $pristineSegment);
        } finally {
            NexusDB::table('comments')->whereIn('id', [$editedId, $pristineId])->delete();
        }
    }

    public function test_anonymous_comment_hides_author_from_regular_viewer(): void
    {
        $owner = $this->createUser();
        $author = $this->createUser();
        $viewer = $this->createUser(['class' => User::CLASS_USER]);
        $torrentId = $this->createTorrent($owner->id);

        $commentId = $this->insertComment($torrentId, $author->id, [
            'text' => 'shy reply',
            'anonymous' => 'yes',
        ]);

        try {
            Livewire::actingAs($viewer, 'nexus-web')
                ->test(TorrentDetail::class, ['id' => $torrentId])
                ->assertSee('data-comment-id="'.$commentId.'"', false)
                ->assertDontSee($author->username)
                ->assertSee('Anonymous');
        } finally {
            NexusDB::table('comments')->where('id', $commentId)->delete();
        }
    }

    public function test_strong_privacy_hides_comment_author_from_regular_viewer(): void
    {
        $owner = $this->createUser();
        $shyAuthor = $this->createUserWithPrivacy('strong');
        $viewer = $this->createUser(['class' => User::CLASS_USER]);
        $torrentId = $this->createTorrent($owner->id);

        $commentId = $this->insertComment($torrentId, $shyAuthor->id, ['text' => 'hi']);

        try {
            Livewire::actingAs($viewer, 'nexus-web')
                ->test(TorrentDetail::class, ['id' => $torrentId])
                ->assertSee('data-comment-id="'.$commentId.'"', false)
                ->assertDontSee($shyAuthor->username)
                ->assertSee('Anonymous');
        } finally {
            NexusDB::table('comments')->where('id', $commentId)->delete();
        }
    }

    public function test_anonymous_author_sees_their_own_comment_username(): void
    {
        $owner = $this->createUser();
        $author = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        $commentId = $this->insertComment($torrentId, $author->id, [
            'text' => 'mine',
            'anonymous' => 'yes',
        ]);

        try {
            Livewire::actingAs($author, 'nexus-web')
                ->test(TorrentDetail::class, ['id' => $torrentId])
                ->assertSee('data-comment-id="'.$commentId.'"', false)
                ->assertSee($author->username);
        } finally {
            NexusDB::table('comments')->where('id', $commentId)->delete();
        }
    }

    public function test_staff_leader_sees_anonymous_comment_author(): void
    {
        $owner = $this->createUser();
        $author = $this->createUser();
        $staff = $this->createUser(['class' => User::CLASS_STAFF_LEADER]);
        $torrentId = $this->createTorrent($owner->id);

        $commentId = $this->insertComment($torrentId, $author->id, [
            'text' => 'staff sees me',
            'anonymous' => 'yes',
        ]);

        try {
            Livewire::actingAs($staff, 'nexus-web')
                ->test(TorrentDetail::class, ['id' => $torrentId])
                ->assertSee('data-comment-id="'.$commentId.'"', false)
                ->assertSee($author->username);
        } finally {
            NexusDB::table('comments')->where('id', $commentId)->delete();
        }
    }

    /**
     * Extract just the `<li …data-comment-id="X">…</li>` segment from the
     * full rendered page so per-comment assertions don't accidentally
     * cross-talk with neighbouring rows.
     */
    private function commentSegment(string $html, int $commentId): string
    {
        $needle = 'data-comment-id="'.$commentId.'"';
        $pos = strpos($html, $needle);
        if ($pos === false) {
            $this->fail('comment '.$commentId.' not present in rendered HTML');
        }
        $end = strpos($html, '</li>', $pos);
        $this->assertNotFalse($end, 'comment '.$commentId.' missing closing </li>');

        return substr($html, $pos, $end - $pos);
    }

    private function createUserWithPrivacy(string $privacy): User
    {
        $user = $this->createUser();
        NexusDB::table('users')->where('id', $user->id)->update(['privacy' => $privacy]);
        $user->refresh();

        return $user;
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
