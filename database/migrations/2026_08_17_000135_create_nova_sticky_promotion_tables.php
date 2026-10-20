<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nova_sticky_promotions')) {
            Schema::create('nova_sticky_promotions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('torrent_id')->default(0);
                $table->unsignedInteger('sponsor_id')->default(0);
                $table->enum('sponsor_anonymous', ['yes', 'no'])->default('no');
                $table->unsignedTinyInteger('promotion_type')->default(0);
                $table->unsignedTinyInteger('original_sp_state')->default(0);
                $table->unsignedTinyInteger('original_promotion_time_type')->default(0);
                $table->dateTime('original_promotion_until')->nullable();
                $table->decimal('coefficient', 8, 2)->default(0);
                $table->decimal('base_hourly_rate', 10, 2)->default(0);
                $table->decimal('occupancy_cost', 12, 2)->default(0);
                $table->decimal('bid_bonus', 12, 2)->default(0);
                $table->decimal('reward_pool', 12, 2)->default(0);
                $table->unsignedInteger('winner_count')->default(1);
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->string('status', 20)->default('active');
                $table->dateTime('settled_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->index(['torrent_id', 'status', 'ends_at'], 'idx_sticky_torrent_status');
                $table->index(['status', 'ends_at'], 'idx_sticky_status_ends');
            });
        }

        if (!Schema::hasTable('nova_sticky_promotion_participants')) {
            Schema::create('nova_sticky_promotion_participants', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('campaign_id')->default(0);
                $table->unsignedInteger('user_id')->default(0);
                $table->unsignedBigInteger('baseline_uploaded')->default(0);
                $table->unsignedBigInteger('baseline_seedtime')->default(0);
                $table->unsignedBigInteger('final_uploaded')->default(0);
                $table->decimal('reward', 12, 2)->default(0);
                $table->enum('is_anonymous', ['yes', 'no'])->default('no');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->unique(['campaign_id', 'user_id'], 'uniq_sticky_campaign_user');
                $table->index('user_id', 'idx_sticky_participant_user');
            });
        }

        if (!Schema::hasTable('nova_sticky_promotion_events')) {
            Schema::create('nova_sticky_promotion_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('campaign_id')->default(0);
                $table->unsignedInteger('user_id')->default(0);
                $table->string('event_type', 20)->default('');
                $table->unsignedInteger('hours_added')->default(0);
                $table->decimal('bid_added', 12, 2)->default(0);
                $table->decimal('reward_added', 12, 2)->default(0);
                $table->unsignedInteger('winner_count_added')->default(0);
                $table->timestamp('created_at')->nullable();

                $table->index('campaign_id', 'idx_sticky_event_campaign');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nova_sticky_promotion_events');
        Schema::dropIfExists('nova_sticky_promotion_participants');
        Schema::dropIfExists('nova_sticky_promotions');
    }
};
