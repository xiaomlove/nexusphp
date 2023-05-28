<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('claims')) {
            return;
        }
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->integer('uid');
            $table->integer('torrent_id')->index();
            $table->integer('snatched_id')->index();
            $table->bigInteger('seed_time_begin')->default(0);
            $table->bigInteger('uploaded_begin')->default(0);
            $table->dateTime('last_settle_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['uid', 'torrent_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('claims');
    }
};
