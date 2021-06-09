<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReadpostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('readposts')) {
            return;
        }
        Schema::create('readposts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('userid')->default(0)->index('userid');
            $table->unsignedMediumInteger('topicid')->default(0)->index('topicid');
            $table->unsignedInteger('lastpostread')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('readposts');
    }
}
