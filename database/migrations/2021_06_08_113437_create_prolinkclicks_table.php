<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProlinkclicksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('prolinkclicks')) {
            return;
        }
        Schema::create('prolinkclicks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('userid')->default(0);
            $table->string('ip', 64)->default('');
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
        Schema::dropIfExists('prolinkclicks');
    }
}
