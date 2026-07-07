<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announce_v2_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedTinyInteger('stream');
            $table->string('event_id', 32);
            $table->char('batch_token', 32)->index();
            $table->boolean('processed')->default(false);
            $table->timestamp('created_at')->useCurrent()->index();
            $table->unique(['stream', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announce_v2_events');
    }
};

