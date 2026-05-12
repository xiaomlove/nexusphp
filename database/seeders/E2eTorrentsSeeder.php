<?php

namespace Database\Seeders;

use App\Models\Torrent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds one deterministic torrent row so the new Livewire
 * `App\Livewire\TorrentDetail` page (`/torrent/{id}`) has a real
 * record to render against during Playwright runs.
 *
 *   id        : 1
 *   name      : E2E Test Torrent
 *   owner     : e2eadmin (id=1, seeded by E2eUsersSeeder)
 *   category  : 401 (Movies, seeded by CategoriesTableSeeder)
 *   visible   : yes
 *   banned    : no
 *
 * Idempotent: if a row with `id=1` already exists, the seeder
 * updates the volatile fields (name, owner, category, dates) so
 * re-running `e2e:bootstrap` doesn't drift the fixture.
 *
 * The `info_hash` column is `binary(20) NULL UNIQUE` after
 * `2024_10_13_035900_change_torrents_table_info_hash_nullable`,
 * so we leave it `null` rather than fabricating bytes and risking
 * a unique-key collision on re-seed.
 */
class E2eTorrentsSeeder extends Seeder
{
    public const TORRENT_ID = 1;

    public const TORRENT_NAME = 'E2E Test Torrent';

    public const TORRENT_DESCR = 'Deterministic torrent fixture for Playwright. See E2eTorrentsSeeder.';

    public const CATEGORY_ID = 401;

    public function run(): void
    {
        $ownerId = (int) User::query()
            ->where('username', 'e2eadmin')
            ->value('id');

        if ($ownerId === 0) {
            $this->command?->warn('  E2eTorrentsSeeder: e2eadmin not found, skipping');

            return;
        }

        $now = now();

        $payload = [
            'name' => self::TORRENT_NAME,
            'filename' => 'e2e-test-torrent.torrent',
            'save_as' => 'e2e-test-torrent',
            'small_descr' => self::TORRENT_DESCR,
            'category' => self::CATEGORY_ID,
            'size' => 1_073_741_824, // 1 GiB
            'added' => $now,
            'type' => 'single',
            'numfiles' => 1,
            'owner' => $ownerId,
            'sp_state' => Torrent::PROMOTION_NORMAL,
            'anonymous' => 'no',
            'visible' => 'yes',
            'banned' => 'no',
            'seeders' => 1,
            'leechers' => 0,
            'times_completed' => 0,
            'hr' => Torrent::HR_NO,
            'last_action' => $now,
        ];

        $existing = DB::table('torrents')->where('id', self::TORRENT_ID)->first();

        if ($existing) {
            DB::table('torrents')->where('id', self::TORRENT_ID)->update($payload);
            $this->command?->info(sprintf('  E2eTorrentsSeeder: updated torrent id=%d', self::TORRENT_ID));

            return;
        }

        DB::table('torrents')->insert(array_merge(['id' => self::TORRENT_ID], $payload));
        $this->command?->info(sprintf('  E2eTorrentsSeeder: created torrent id=%d', self::TORRENT_ID));
    }
}
