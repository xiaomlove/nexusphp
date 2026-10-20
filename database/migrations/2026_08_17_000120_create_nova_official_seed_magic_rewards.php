<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nova_official_seed_magic_reward_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->index();
            $table->string('period_key', 20);
            $table->decimal('reward_amount', 14, 5)->default(0);
            $table->decimal('formula_bonus', 14, 5)->default(0);
            $table->decimal('official_percent', 14, 5)->default(0);
            $table->decimal('base_factor', 10, 5)->default(1);
            $table->unsignedSmallInteger('torrent_count')->default(0);
            $table->text('torrent_ids')->nullable();
            $table->dateTime('created_at');
            $table->unique(['user_id', 'period_key'], 'nova_official_seed_magic_user_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nova_official_seed_magic_reward_events');
    }
};
