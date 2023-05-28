<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTorrentTagsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('torrent_tags', function (Blueprint $table) {
            $table->id();
            $table->integer('torrent_id');
            $table->integer('tag_id')->index();
            $table->timestamps();
            $table->unique(['torrent_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('torrent_tags');
    }
}
