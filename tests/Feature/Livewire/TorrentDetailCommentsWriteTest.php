<?php

namespace Tests\Feature\Livewire;

use App\Livewire\TorrentDetail;
use App\Models\CommentEdit;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class TorrentDetailCommentsWriteTest extends FeatureTestCase
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

    public function test_authed_user_can_post_comment_and_increments_torrent_counter(): void
    {
        $owner = $this->createUser();
        $author = $this->createUser();
        $torrentId = $this->createTorrent($owner->id, ['comments' => 5]);

        Livewire::actingAs($author, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->set('newCommentBody', 'hello world')
            ->call('postComment')
            ->assertHasNoErrors()
            ->assertSet('newCommentBody', '');

        $row = NexusDB::table('comments')
            ->where('torrent', $torrentId)
            ->where('user', $author->id)
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('hello world', ((array) $row)['text']);
        $this->assertSame('hello world', ((array) $row)['ori_text']);

        $count = (int) NexusDB::table('torrents')->where('id', $torrentId)->value('comments');
        $this->assertSame(6, $count);

        $lastComment = NexusDB::table('users')->where('id', $author->id)->value('last_comment');
        $this->assertNotNull($lastComment);
    }

    public function test_empty_body_is_rejected_with_validation_error(): void
    {
        $author = $this->createUser();
        $torrentId = $this->createTorrent($this->createUser()->id);

        Livewire::actingAs($author, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->set('newCommentBody', '   ')
            ->call('postComment')
            ->assertHasErrors('newCommentBody');

        $this->assertSame(0, NexusDB::table('comments')->where('torrent', $torrentId)->count());
    }

    public function test_guest_cannot_post_comment(): void
    {
        $torrentId = $this->createTorrent($this->createUser()->id);

        Livewire::test(TorrentDetail::class, ['id' => $torrentId])
            ->set('newCommentBody', 'guest reply')
            ->call('postComment');

        $this->assertSame(0, NexusDB::table('comments')->where('torrent', $torrentId)->count());
    }

    public function test_parked_account_cannot_post_comment(): void
    {
        $author = $this->createUser();
        NexusDB::table('users')->where('id', $author->id)->update(['parked' => 'yes']);
        $torrentId = $this->createTorrent($this->createUser()->id);

        Livewire::actingAs($author, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->set('newCommentBody', 'I am parked')
            ->call('postComment')
            ->assertHasErrors('newCommentBody');

        $this->assertSame(0, NexusDB::table('comments')->where('torrent', $torrentId)->count());
    }

    public function test_anti_flood_blocks_second_comment_within_window(): void
    {
        $author = $this->createUser();
        NexusDB::table('users')
            ->where('id', $author->id)
            ->update(['last_comment' => Carbon::now()->subSeconds(3)->toDateTimeString()]);
        $torrentId = $this->createTorrent($this->createUser()->id);

        Livewire::actingAs($author, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->set('newCommentBody', 'too fast')
            ->call('postComment')
            ->assertHasErrors('newCommentBody');

        $this->assertSame(0, NexusDB::table('comments')->where('torrent', $torrentId)->count());
    }

    public function test_staff_with_commanage_bypasses_anti_flood(): void
    {
        $staff = $this->createUser(['class' => User::CLASS_STAFF_LEADER]);
        NexusDB::table('users')
            ->where('id', $staff->id)
            ->update(['last_comment' => Carbon::now()->subSeconds(1)->toDateTimeString()]);
        $torrentId = $this->createTorrent($this->createUser()->id);

        Livewire::actingAs($staff, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->set('newCommentBody', 'staff override')
            ->call('postComment')
            ->assertHasNoErrors();

        $this->assertSame(1, NexusDB::table('comments')->where('torrent', $torrentId)->count());
    }

    public function test_author_can_edit_own_comment_and_snapshot_is_stored(): void
    {
        $author = $this->createUser();
        $torrentId = $this->createTorrent($this->createUser()->id);
        $commentId = $this->insertComment($torrentId, $author->id, ['text' => 'original']);

        Livewire::actingAs($author, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->call('startEditComment', $commentId)
            ->assertSet('editingCommentId', $commentId)
            ->assertSet('editingBody', 'original')
            ->set('editingBody', 'revised')
            ->call('updateComment', $commentId)
            ->assertHasNoErrors()
            ->assertSet('editingCommentId', null);

        $row = (array) NexusDB::table('comments')->where('id', $commentId)->first();
        $this->assertSame('revised', $row['text']);
        $this->assertNotNull($row['editdate']);
        $this->assertSame($author->id, (int) $row['editedby']);

        $snapshots = CommentEdit::query()->where('commentid', $commentId)->get();
        $this->assertCount(1, $snapshots);
        $this->assertSame('original', $snapshots[0]->body_before);
        $this->assertSame($author->id, (int) $snapshots[0]->editor_userid);
    }

    public function test_non_author_without_commanage_cannot_edit_comment(): void
    {
        $author = $this->createUser();
        $stranger = $this->createUser(['class' => User::CLASS_USER]);
        $torrentId = $this->createTorrent($this->createUser()->id);
        $commentId = $this->insertComment($torrentId, $author->id, ['text' => 'original']);

        Livewire::actingAs($stranger, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->call('startEditComment', $commentId)
            ->assertSet('editingCommentId', null);

        $this->assertSame('original', NexusDB::table('comments')->where('id', $commentId)->value('text'));
        $this->assertSame(0, CommentEdit::query()->where('commentid', $commentId)->count());
    }

    public function test_staff_with_commanage_can_edit_other_users_comment(): void
    {
        $author = $this->createUser();
        $staff = $this->createUser(['class' => User::CLASS_STAFF_LEADER]);
        $torrentId = $this->createTorrent($this->createUser()->id);
        $commentId = $this->insertComment($torrentId, $author->id, ['text' => 'original']);

        Livewire::actingAs($staff, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->call('startEditComment', $commentId)
            ->set('editingBody', 'moderated')
            ->call('updateComment', $commentId)
            ->assertHasNoErrors();

        $row = (array) NexusDB::table('comments')->where('id', $commentId)->first();
        $this->assertSame('moderated', $row['text']);
        $this->assertSame($staff->id, (int) $row['editedby']);

        $snapshots = CommentEdit::query()->where('commentid', $commentId)->get();
        $this->assertCount(1, $snapshots);
        $this->assertSame($staff->id, (int) $snapshots[0]->editor_userid);
    }

    public function test_empty_edit_body_is_rejected(): void
    {
        $author = $this->createUser();
        $torrentId = $this->createTorrent($this->createUser()->id);
        $commentId = $this->insertComment($torrentId, $author->id, ['text' => 'original']);

        Livewire::actingAs($author, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->call('startEditComment', $commentId)
            ->set('editingBody', '   ')
            ->call('updateComment', $commentId)
            ->assertHasErrors('editingBody');

        $this->assertSame('original', NexusDB::table('comments')->where('id', $commentId)->value('text'));
    }

    public function test_staff_with_commanage_can_delete_comment_and_decrements_counter(): void
    {
        $author = $this->createUser();
        $staff = $this->createUser(['class' => User::CLASS_STAFF_LEADER]);
        $torrentId = $this->createTorrent($this->createUser()->id, ['comments' => 3]);
        $commentId = $this->insertComment($torrentId, $author->id);

        Livewire::actingAs($staff, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->call('deleteComment', $commentId);

        $this->assertSame(0, NexusDB::table('comments')->where('id', $commentId)->count());
        $this->assertSame(2, (int) NexusDB::table('torrents')->where('id', $torrentId)->value('comments'));
    }

    public function test_non_staff_user_cannot_delete_even_own_comment(): void
    {
        $author = $this->createUser(['class' => User::CLASS_USER]);
        $torrentId = $this->createTorrent($this->createUser()->id, ['comments' => 1]);
        $commentId = $this->insertComment($torrentId, $author->id);

        Livewire::actingAs($author, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->call('deleteComment', $commentId);

        $this->assertSame(1, NexusDB::table('comments')->where('id', $commentId)->count());
        $this->assertSame(1, (int) NexusDB::table('torrents')->where('id', $torrentId)->value('comments'));
    }

    public function test_cannot_edit_comment_belonging_to_other_torrent(): void
    {
        $author = $this->createUser();
        $torrentA = $this->createTorrent($this->createUser()->id);
        $torrentB = $this->createTorrent($this->createUser()->id);
        $commentOnA = $this->insertComment($torrentA, $author->id, ['text' => 'on-A']);

        Livewire::actingAs($author, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentB])
            ->call('startEditComment', $commentOnA)
            ->assertSet('editingCommentId', null);

        $this->assertSame('on-A', NexusDB::table('comments')->where('id', $commentOnA)->value('text'));
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
