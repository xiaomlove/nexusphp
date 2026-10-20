<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The site navigation is database driven (include/nova_navigation.php renders the
 * `navigations` table through the `nexus_menu` filter). The tables were created by
 * hand on the live install, so reproduce them here and seed the entries that used
 * to be hard coded in menu().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('navigations')) {
            Schema::create('navigations', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('url')->default('');
                $table->unsignedTinyInteger('is_external')->default(0);
                $table->unsignedInteger('parent_id')->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedTinyInteger('new_tab')->default(0);
                $table->string('permission')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->index(['parent_id', 'sort_order']);
            });
        }

        if (!Schema::hasTable('nova_navigation_snapshots')) {
            Schema::create('nova_navigation_snapshots', function (Blueprint $table) {
                $table->increments('id');
                $table->string('snapshot_key')->unique();
                $table->longText('payload');
                $table->dateTime('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('nova_navigation_audit_logs')) {
            Schema::create('nova_navigation_audit_logs', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('operator_id');
                $table->string('action', 32);
                $table->unsignedInteger('target_id')->nullable();
                $table->longText('before_data')->nullable();
                $table->longText('after_data')->nullable();
                $table->string('ip', 64)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->index(['operator_id', 'created_at']);
            });
        }

        $this->seedEntries();
    }

    public function down(): void
    {
        if (Schema::hasTable('navigations')) {
            DB::table('navigations')
                ->whereIn('url', ['seedguard.php', 'donate.php'])
                ->where('parent_id', 0)
                ->delete();
        }
    }

    /**
     * Seed the menu entries that were previously hard coded, skipping any entry
     * that already exists on this install.
     */
    private function seedEntries(): void
    {
        if (!Schema::hasTable('navigations')) {
            return;
        }

        $columns = Schema::getColumnListing('navigations');
        $defaults = [
            'name' => '',
            'url' => '',
            'is_external' => 0,
            'parent_id' => 0,
            'sort_order' => 0,
            'new_tab' => 0,
            'permission' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $entries = [
            ['name' => '保种区', 'url' => 'seedguard.php', 'sort_order' => 45],
            [
                'name' => '捐赠',
                'url' => 'donate.php',
                'sort_order' => 95,
                'permission' => 'setting:misc.donation_public_enabled=yes',
            ],
        ];

        foreach ($entries as $entry) {
            $row = array_intersect_key($entry + $defaults, array_flip($columns));
            $exists = DB::table('navigations')
                ->where('url', $entry['url'])
                ->where('parent_id', 0)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('navigations')->insert($row);
        }
    }
};
