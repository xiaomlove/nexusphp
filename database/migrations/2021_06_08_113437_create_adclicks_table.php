<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdclicksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('adclicks')) {
            return;
        }
        Schema::create('adclicks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('adid')->nullable();
            $table->unsignedInteger('userid')->nullable();
            $table->dateTime('added')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('adclicks');
    }
}
