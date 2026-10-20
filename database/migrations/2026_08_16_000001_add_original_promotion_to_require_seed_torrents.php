<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('require_seed_torrents', function (Blueprint $table) {
            $table->unsignedTinyInteger('original_sp_state')->default(1)->after('torrent_id');
            $table->unsignedTinyInteger('original_promotion_time_type')->default(0)->after('original_sp_state');
            $table->dateTime('original_promotion_until')->nullable()->after('original_promotion_time_type');
        });
    }

    public function down(): void
    {
        Schema::table('require_seed_torrents', function (Blueprint $table) {
            $table->dropColumn([
                'original_sp_state',
                'original_promotion_time_type',
                'original_promotion_until',
            ]);
        });
    }
};
