<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateForummodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('forummods')) {
            return;
        }
        Schema::create('forummods', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('forumid')->default(0)->index('forumid');
            $table->unsignedMediumInteger('userid')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('forummods');
    }
}
