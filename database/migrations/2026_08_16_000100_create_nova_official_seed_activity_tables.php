<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nova_official_seed_campaigns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('torrent_id')->unique();
            $table->unsignedBigInteger('size_bytes');
            $table->dateTime('qualified_at');
            $table->string('status', 16)->default('active')->index();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('nova_official_seed_participants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('campaign_id')->index();
            $table->unsignedInteger('torrent_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->unsignedBigInteger('baseline_seedtime')->default(0);
            $table->unsignedBigInteger('seed_seconds')->default(0);
            $table->dateTime('enrolled_at');
            $table->dateTime('last_seen_at')->nullable();
            $table->unsignedBigInteger('reward_15_bytes')->default(0);
            $table->dateTime('reward_15_at')->nullable();
            $table->unsignedBigInteger('reward_30_bytes')->default(0);
            $table->dateTime('reward_30_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'user_id'], 'nova_official_seed_campaign_user_unique');
        });

        Schema::create('nova_official_seed_reward_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('participant_id')->index();
            $table->unsignedInteger('torrent_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->unsignedTinyInteger('tier_days');
            $table->unsignedBigInteger('reward_bytes');
            $table->unsignedBigInteger('uploaded_before');
            $table->unsignedBigInteger('uploaded_after');
            $table->dateTime('created_at');
            $table->unique(['participant_id', 'tier_days'], 'nova_official_seed_participant_tier_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nova_official_seed_reward_events');
        Schema::dropIfExists('nova_official_seed_participants');
        Schema::dropIfExists('nova_official_seed_campaigns');
    }
};
