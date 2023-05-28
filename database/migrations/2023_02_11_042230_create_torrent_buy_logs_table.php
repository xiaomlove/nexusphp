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
        Schema::create('torrent_buy_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('uid')->index();
            $table->integer('torrent_id')->index();
            $table->integer('price')->index();
            $table->string('channel');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('torrent_buy_logs');
    }
};
