<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Test-only umbrella seeder.
 *
 * Runs the existing `DatabaseSeeder` (lookup tables: categories, languages,
 * stylesheets, codecs, ...) and additionally seeds the `settings` table from
 * `nexus/Install/settings.default.php`.
 *
 * The Settings seeder is intentionally NOT part of `DatabaseSeeder`, because
 * `saveSetting()` upserts and would clobber operator-customised values in
 * production. In CI we always start from a fresh `nexus_test` database, so
 * the upsert is safe and necessary.
 */
class TestingDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        $this->call(SettingsTableSeeder::class);
    }
}
