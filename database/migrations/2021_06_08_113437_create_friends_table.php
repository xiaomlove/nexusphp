<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFriendsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('friends')) {
            return;
        }
        Schema::create('friends', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('userid')->default(0);
            $table->unsignedMediumInteger('friendid')->default(0);
            $table->unique(['userid', 'friendid'], 'userfriend');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('friends');
    }
}
