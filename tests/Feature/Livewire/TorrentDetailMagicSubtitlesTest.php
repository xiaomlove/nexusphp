<?php

namespace Tests\Feature\Livewire;

use App\Livewire\TorrentDetail;
use App\Models\Reward;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class TorrentDetailMagicSubtitlesTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    private int $categoryId = 0;

    private int $languageId = 0;

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

        $this->languageId = (int) NexusDB::table('language')->insertGetId([
            'lang_name' => 'English-Test',
            'flagpic' => 'en.gif',
            'sub_lang' => 1,
            'rule_lang' => 0,
            'site_lang' => 0,
            'site_lang_folder' => 'en',
            'trans_state' => 'up-to-date',
        ]);

        NexusDB::table('settings')->where('name', 'torrent.reward_bonus_options')->delete();
        NexusDB::table('settings')->where('name', 'torrent.reward_times_limit')->delete();
    }

    public function test_subtitles_block_is_empty_when_no_rows(): void
    {
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($this->createUser()->id);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-test-id="subtitles-block"')
            ->assertSeeHtml('data-subtitles-count="0"')
            ->assertSeeHtml('data-test-id="subtitles-empty"');
    }

    public function test_subtitles_block_lists_existing_subtitles(): void
    {
        $owner = $this->createUser();
        $uploader = $this->createUser();
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        $subId = $this->insertSubtitle($torrentId, $uploader->id, 'pack.srt');

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-subtitles-count="1"')
            ->assertSeeHtml('data-subtitle-id="'.$subId.'"')
            ->assertSeeHtml('href="/downloadsubs.php?torrentid='.$torrentId.'&amp;subid='.$subId.'"')
            ->assertSee($uploader->username);
    }

    public function test_subtitles_block_shows_anonymous_for_non_privileged_viewer(): void
    {
        $owner = $this->createUser();
        $uploader = $this->createUser();
        $viewer = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        $this->insertSubtitle($torrentId, $uploader->id, 'pack.srt', anonymous: true);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-subtitles-count="1"')
            ->assertSee('anonymous')
            ->assertDontSee($uploader->username);
    }

    public function test_subtitles_upload_form_appears_for_owner(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-test-id="subtitles-upload-form"')
            ->assertSeeHtml('data-test-id="subtitles-upload-button"');
    }

    public function test_subtitles_upload_form_absent_for_guests(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSeeHtml('data-test-id="subtitles-upload-form"');
    }

    public function test_magic_block_renders_for_member_with_bonus(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser(['seedbonus' => 50000]);
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-test-id="magic-block"')
            ->assertSeeHtml('data-test-id="magic-option-button"')
            ->assertSeeHtml('wire:click="addMagic(50)"');
    }

    public function test_magic_block_hides_buttons_for_owner(): void
    {
        $owner = $this->createUser(['seedbonus' => 50000]);
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-test-id="magic-block"')
            ->assertDontSeeHtml('data-test-id="magic-option-button"')
            ->assertDontSeeHtml('data-test-id="magic-insufficient-button"');
    }

    public function test_magic_block_shows_insufficient_when_balance_is_zero(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser(['seedbonus' => 0]);
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-test-id="magic-insufficient-button"')
            ->assertDontSeeHtml('data-test-id="magic-option-button"');
    }

    public function test_magic_block_shows_given_state_when_viewer_already_rewarded(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser(['seedbonus' => 50000]);
        $torrentId = $this->createTorrent($owner->id);

        $this->insertMagic($torrentId, $viewer->id, 100);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-magic-has-given="yes"')
            ->assertSeeHtml('data-test-id="magic-given-button"')
            ->assertDontSeeHtml('data-test-id="magic-option-button"');
    }

    public function test_magic_block_is_hidden_for_guests(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::test(TorrentDetail::class, ['id' => $torrentId])
            ->assertDontSeeHtml('data-test-id="magic-option-button"')
            ->assertDontSeeHtml('data-test-id="magic-given-button"')
            ->assertDontSeeHtml('data-test-id="magic-insufficient-button"');
    }

    public function test_magic_summary_reflects_existing_rewards(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser(['seedbonus' => 50000]);
        $other = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        $this->insertMagic($torrentId, $other->id, 200);
        $this->insertMagic($torrentId, $other->id, 100);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->assertSeeHtml('data-magic-unique-users="1"')
            ->assertSeeHtml('data-magic-total-value="300"');
    }

    public function test_add_magic_credits_owner_and_records_reward(): void
    {
        $owner = $this->createUser(['seedbonus' => 1000]);
        $viewer = $this->createUser(['seedbonus' => 50000]);
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->call('addMagic', 100)
            ->assertHasNoErrors()
            ->assertSet('magicFlash', 'Magic given (+100).');

        $this->assertSame(
            1,
            Reward::query()->where('torrentid', $torrentId)->where('userid', $viewer->id)->count(),
        );
        $this->assertSame(49900, (int) User::query()->find($viewer->id)->seedbonus);
        $this->assertSame(1100, (int) User::query()->find($owner->id)->seedbonus);
    }

    public function test_add_magic_rejects_invalid_value(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser(['seedbonus' => 50000]);
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->call('addMagic', 17)
            ->assertHasErrors('magic');

        $this->assertSame(0, Reward::query()->where('torrentid', $torrentId)->count());
    }

    public function test_add_magic_rejects_self_reward(): void
    {
        $owner = $this->createUser(['seedbonus' => 50000]);
        $torrentId = $this->createTorrent($owner->id);

        Livewire::actingAs($owner, 'nexus-web')
            ->test(TorrentDetail::class, ['id' => $torrentId])
            ->call('addMagic', 100)
            ->assertHasErrors('magic');

        $this->assertSame(0, Reward::query()->where('torrentid', $torrentId)->count());
    }

    public function test_add_magic_is_silent_for_guests(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        Livewire::test(TorrentDetail::class, ['id' => $torrentId])
            ->call('addMagic', 100);

        $this->assertSame(0, Reward::query()->where('torrentid', $torrentId)->count());
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge([
                'lang' => self::ENGLISH_LANGUAGE_ID,
                'class' => User::CLASS_USER,
                'seedbonus' => 0,
            ], $overrides),
        );
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTorrent(int $ownerId, array $overrides = []): int
    {
        return (int) NexusDB::table('torrents')->insertGetId(array_merge([
            'name' => 'magic-test-'.bin2hex(random_bytes(4)),
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

    private function insertSubtitle(int $torrentId, int $uploaderId, string $title, bool $anonymous = false): int
    {
        return (int) NexusDB::table('subs')->insertGetId([
            'torrent_id' => $torrentId,
            'lang_id' => $this->languageId,
            'title' => $title,
            'filename' => 'fixture.srt',
            'added' => Carbon::now()->toDateTimeString(),
            'size' => 256,
            'uppedby' => $uploaderId,
            'anonymous' => $anonymous ? 'yes' : 'no',
            'hits' => 0,
            'ext' => 'srt',
        ]);
    }

    private function insertMagic(int $torrentId, int $userId, int $value): int
    {
        return (int) NexusDB::table('magic')->insertGetId([
            'torrentid' => $torrentId,
            'userid' => $userId,
            'value' => $value,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
    }
}
