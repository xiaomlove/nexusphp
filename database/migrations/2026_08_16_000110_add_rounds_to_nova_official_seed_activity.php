<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nova_official_seed_rounds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->string('status', 16)->default('scheduled')->index();
            $table->unsignedInteger('created_by')->default(0);
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::table('nova_official_seed_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('round_id')->nullable()->after('id')->index();
            $table->unsignedInteger('ending_seeders')->nullable()->after('status');
            $table->string('outcome', 24)->nullable()->after('ending_seeders')->index();
            $table->dropUnique('nova_official_seed_campaigns_torrent_id_unique');
            $table->unique(['round_id', 'torrent_id'], 'nova_official_seed_round_torrent_unique');
        });

        $firstQualified = DB::table('nova_official_seed_campaigns')->min('qualified_at') ?: now()->toDateTimeString();
        $roundId = DB::table('nova_official_seed_rounds')->insertGetId([
            'starts_at' => $firstQualified,
            'ends_at' => now()->addDays(30)->toDateTimeString(),
            'status' => 'active',
            'created_by' => 0,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
        DB::table('nova_official_seed_campaigns')->whereNull('round_id')->update(['round_id' => $roundId]);

    }

    public function down(): void
    {
        Schema::table('nova_official_seed_campaigns', function (Blueprint $table) {
            $table->dropUnique('nova_official_seed_round_torrent_unique');
            $table->dropIndex(['round_id']);
            $table->dropIndex(['outcome']);
            $table->dropColumn(['round_id', 'ending_seeders', 'outcome']);
            $table->unique('torrent_id');
        });
        Schema::dropIfExists('nova_official_seed_rounds');
    }
};
