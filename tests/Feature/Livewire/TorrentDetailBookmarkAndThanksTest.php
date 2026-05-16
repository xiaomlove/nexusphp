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
 * Pins the Phase 3 — Modern UI A3 contract for the Bookmark toggle +
 * Say-thanks button + thanks-by list on `App\Livewire\TorrentDetail`
 * (PR-3 of the second `details.php` Strangler-Fig wave).
 *
 * Bookmark toggle (legacy: `public/bookmark.php` → already migrated to
 * `App\Http\Controllers\Legacy\BookmarkController`):
 *
 *   - Persists a row in `bookmarks` on first click.
 *   - Removes the row on the second click (idempotent toggle).
 *   - Defers per-user cache invalidation and ES re-index to the same
 *     helpers the legacy controller uses.
 *   - The action defends in depth and silently no-ops for guests; the
 *     view, however, hides the whole actions card from anonymous
 *     viewers.
 *
 * Say-thanks button (legacy: `public/thanks.php` → migrated to
 * `App\Http\Controllers\Legacy\ThanksController`):
 *
 *   - Inserts a row in `thanks` on first click.
 *   - Already-thanked viewers are a no-op (the UNIQUE KEY on
 *     `(torrentid,userid)` would 1062 otherwise).
 *   - Credits the configured bonus to both the thanker and the
 *     torrent owner — gated on the global `tweak.bonus` toggle.
 *   - The thanks-by panel surfaces the most recent 20 usernames plus
 *     "X users in total" when the count exceeds the LIMIT.
 */
class TorrentDetailBookmarkAndThanksTest extends FeatureTestCase
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

    public function test_actions_card_is_hidden_from_guests(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        // No `actingAs` here — the Livewire mount runs anonymously.
        Livewire::test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSee('data-test-id="torrent-actions"', false);
    }

    public function test_bookmark_toggle_inserts_and_removes_row(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        $component = Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId]);

        $this->assertFalse($this->bookmarkExists($viewer->id, $torrentId));

        $component->call('toggleBookmark');

        $this->assertTrue($this->bookmarkExists($viewer->id, $torrentId));
        $component->assertSeeHtml('data-bookmarked="yes"');

        $component->call('toggleBookmark');

        $this->assertFalse($this->bookmarkExists($viewer->id, $torrentId));
        $component->assertSeeHtml('data-bookmarked="no"');
    }

    public function test_bookmark_toggle_does_not_affect_other_users(): void
    {
        $owner = $this->createUser();
        $alice = $this->createUser();
        $bob = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        NexusDB::table('bookmarks')->insert([
            'userid' => $bob->id,
            'torrentid' => $torrentId,
        ]);

        Livewire::actingAs($alice, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->call('toggleBookmark');

        $this->assertTrue($this->bookmarkExists($alice->id, $torrentId));
        $this->assertTrue($this->bookmarkExists($bob->id, $torrentId));
    }

    public function test_say_thanks_inserts_row_and_marks_viewer_thanked(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        $component = Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-thanked="no"')
            ->assertSee('Say thanks')
            ->call('sayThanks');

        $this->assertTrue($this->thanksExists($viewer->id, $torrentId));
        $component->assertSeeHtml('data-thanked="yes"')
            ->assertSee('Thanks said');
    }

    public function test_say_thanks_is_noop_when_already_thanked(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        NexusDB::table('thanks')->insert([
            'userid' => $viewer->id,
            'torrentid' => $torrentId,
        ]);
        $initialCount = (int) NexusDB::table('thanks')->where('torrentid', $torrentId)->count();

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->call('sayThanks');

        $this->assertSame(
            $initialCount,
            (int) NexusDB::table('thanks')->where('torrentid', $torrentId)->count(),
        );
    }

    public function test_say_thanks_credits_bonus_to_thanker_and_owner_when_tweak_enabled(): void
    {
        // `users.seedbonus` is `decimal(20,1)` in the schema — values
        // are stored with a single decimal place, so we deliberately
        // pick bonus increments that already fit that precision.
        // Otherwise MySQL silently half-up-rounds (e.g. 11.25 → 11.3)
        // and the assertion would chase a phantom 0.05 drift.
        $this->seedSetting('tweak.bonus', 'enable');
        $this->seedSetting('bonus.saythanks', '0.5');
        $this->seedSetting('bonus.receivethanks', '1.5');

        $owner = $this->createUser(['seedbonus' => '10.0']);
        $viewer = $this->createUser(['seedbonus' => '2.0']);
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->call('sayThanks');

        $this->assertEqualsWithDelta(
            2.5,
            (float) NexusDB::table('users')->where('id', $viewer->id)->value('seedbonus'),
            0.001,
        );
        $this->assertEqualsWithDelta(
            11.5,
            (float) NexusDB::table('users')->where('id', $owner->id)->value('seedbonus'),
            0.001,
        );
    }

    public function test_say_thanks_skips_bonus_when_tweak_disabled(): void
    {
        $this->seedSetting('tweak.bonus', 'disable');
        $this->seedSetting('bonus.saythanks', '0.50');
        $this->seedSetting('bonus.receivethanks', '1.25');

        $owner = $this->createUser(['seedbonus' => '10.00']);
        $viewer = $this->createUser(['seedbonus' => '2.00']);
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->call('sayThanks');

        $this->assertEqualsWithDelta(
            2.00,
            (float) NexusDB::table('users')->where('id', $viewer->id)->value('seedbonus'),
            0.001,
        );
        $this->assertEqualsWithDelta(
            10.00,
            (float) NexusDB::table('users')->where('id', $owner->id)->value('seedbonus'),
            0.001,
        );
        // The row should still be inserted; only the bonus is skipped.
        $this->assertTrue($this->thanksExists($viewer->id, $torrentId));
    }

    public function test_thanks_by_list_renders_recent_usernames_with_total_count(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        $recentThankers = [];
        for ($i = 0; $i < 22; $i++) {
            $user = $this->createUser(['username' => 'thanker-'.bin2hex(random_bytes(3))]);
            NexusDB::table('thanks')->insert([
                'userid' => $user->id,
                'torrentid' => $torrentId,
            ]);
            $recentThankers[] = $user->username;
        }

        $component = Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId]);

        $component->assertSeeHtml('data-thanks-total="22"');
        $component->assertSeeHtml('data-test-id="thanks-recent"');
        $component->assertSeeHtml('data-test-id="thanks-more"');
        $component->assertSee('and 22 users in total');

        $rendered = (string) $component->html();
        // The 20 most recent thankers (last-inserted) must appear; the
        // first two must not.
        for ($i = 2; $i < 22; $i++) {
            $this->assertStringContainsString(
                $recentThankers[$i],
                $rendered,
                'Expected recent thanker '.$recentThankers[$i].' in the thanks-by list',
            );
        }
        $this->assertStringNotContainsString($recentThankers[0], $rendered);
        $this->assertStringNotContainsString($recentThankers[1], $rendered);
    }

    public function test_actions_card_renders_empty_thanks_placeholder(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSee('No thanks yet')
            ->assertSeeHtml('data-thanks-total="0"');
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createUser(array $overrides = []): User
    {
        // Modern UI bookmark + thanks are not class-gated, so the
        // default `CLASS_USER` is fine here — the static-cached
        // `authority` permission matrix that bit the NFO viewer tests
        // (see {@see TorrentDetailTechnicalInfoAndNfoTest::createUser})
        // does not influence either action.
        return $this->createLegacyUser(
            overrides: array_merge([
                'lang' => self::ENGLISH_LANGUAGE_ID,
                'class' => User::CLASS_USER,
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

    private function bookmarkExists(int $userId, int $torrentId): bool
    {
        return NexusDB::table('bookmarks')
            ->where('userid', $userId)
            ->where('torrentid', $torrentId)
            ->exists();
    }

    private function thanksExists(int $userId, int $torrentId): bool
    {
        return NexusDB::table('thanks')
            ->where('userid', $userId)
            ->where('torrentid', $torrentId)
            ->exists();
    }

    /**
     * Write a single key into the `settings` table the same way the
     * Laravel admin UI writes them; inside `DatabaseTransactions` the
     * row rolls back on teardown.
     */
    private function seedSetting(string $name, string $value): void
    {
        $now = Carbon::now()->toDateTimeString();
        NexusDB::table('settings')->updateOrInsert(
            ['name' => $name],
            [
                'value' => $value,
                'autoload' => 'yes',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }
}
