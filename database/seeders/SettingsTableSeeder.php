<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the `settings` table with the same default values that
 * `nexus/Install/Install::saveSettings()` writes during a fresh install.
 *
 * Required for any Feature test that exercises legacy code, because
 * `Setting::getDefaultLang()` and friends throw a `TypeError` when the
 * matching `main.*` / `security.*` row is absent.
 *
 * Uses raw upsert (`DB::table()->upsert`) instead of the legacy
 * `saveSetting()` helper so it runs from `php artisan db:seed` without
 * requiring `dbconn()` to have been called first.
 */
class SettingsTableSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = require dirname(__DIR__, 2).'/nexus/Install/settings.default.php';

        $now = now();
        $rows = [];
        foreach ($defaults as $prefix => $group) {
            foreach ($group as $name => $value) {
                $rows[] = [
                    'name' => strtolower((string) $prefix).'.'.$name,
                    'value' => is_array($value) ? json_encode($value) : (string) ($value ?? ''),
                    'autoload' => 'yes',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('settings')->upsert($rows, ['name'], ['value', 'updated_at']);
        }
    }
}
