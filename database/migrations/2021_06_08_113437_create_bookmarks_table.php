<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBookmarksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('bookmarks')) {
            return;
        }
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('torrentid')->default(0);
            $table->unsignedMediumInteger('userid')->default(0);
            $table->index(['userid', 'torrentid'], 'userid_torrentid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bookmarks');
    }
}
