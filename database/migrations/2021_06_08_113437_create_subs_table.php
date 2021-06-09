<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('subs')) {
            return;
        }
        Schema::create('subs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('torrent_id')->default(0);
            $table->unsignedSmallInteger('lang_id')->default(0);
            $table->string('title')->default('');
            $table->string('filename')->default('');
            $table->dateTime('added')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedMediumInteger('uppedby')->default(0);
            $table->enum('anonymous', ['yes', 'no'])->default('no');
            $table->unsignedMediumInteger('hits')->default(0);
            $table->string('ext', 10)->default('');
            $table->index(['torrent_id', 'lang_id'], 'torrentid_langid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subs');
    }
}
