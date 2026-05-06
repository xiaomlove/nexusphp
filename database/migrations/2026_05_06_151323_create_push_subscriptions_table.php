<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('push_subscriptions')) {
            return;
        }
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('userid')->index();
            $table->string('endpoint', 1000);
            $table->string('endpoint_hash', 64)->unique();
            $table->string('p256dh', 255)->default('');
            $table->string('auth', 255)->default('');
            $table->string('content_encoding', 32)->default('aes128gcm');
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
