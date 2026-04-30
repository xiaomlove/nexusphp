<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const URI = 'styles/Modern2025/';
    private const NAME = 'Modern 2025';
    private const DESIGNER = 'devin';
    private const COMMENT = 'Responsive theme with light/dark toggle and modern typography.';
    private const ADDICODE = '<script src="styles/Modern2025/init.js"></script>';
    private const STYLESHEET_ID = 100;
    private const SETTING_NAME = 'main.defstylesheet';

    /**
     * Register the new "Modern 2025" stylesheet and set it as the default
     * for new users. Existing users keep whatever theme they have selected
     * in their profile.
     */
    public function up(): void
    {
        $existing = DB::table('stylesheets')
            ->where('uri', self::URI)
            ->first();

        if ($existing === null) {
            DB::table('stylesheets')->insert([
                'id' => self::STYLESHEET_ID,
                'uri' => self::URI,
                'name' => self::NAME,
                'addicode' => self::ADDICODE,
                'designer' => self::DESIGNER,
                'comment' => self::COMMENT,
            ]);
            $newId = self::STYLESHEET_ID;
        } else {
            // Keep the existing id but make sure metadata is up to date.
            DB::table('stylesheets')
                ->where('uri', self::URI)
                ->update([
                    'name' => self::NAME,
                    'addicode' => self::ADDICODE,
                    'designer' => self::DESIGNER,
                    'comment' => self::COMMENT,
                ]);
            $newId = $existing->id;
        }

        $now = date('Y-m-d H:i:s');
        $setting = DB::table('settings')
            ->where('name', self::SETTING_NAME)
            ->first();

        if ($setting === null) {
            DB::table('settings')->insert([
                'name' => self::SETTING_NAME,
                'value' => (string) $newId,
                'autoload' => 'yes',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('settings')
                ->where('name', self::SETTING_NAME)
                ->update([
                    'value' => (string) $newId,
                    'updated_at' => $now,
                ]);
        }
    }

    /**
     * Remove the stylesheet and fall back to the legacy Classic theme (id 4)
     * as the default. Per-user `users.stylesheet` references are not touched
     * here — NexusPHP already falls back to the default when a stylesheet id
     * is missing (see `get_css_row()` in include/functions.php).
     */
    public function down(): void
    {
        DB::table('stylesheets')
            ->where('uri', self::URI)
            ->delete();

        DB::table('settings')
            ->where('name', self::SETTING_NAME)
            ->update([
                'value' => '4',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }
};
