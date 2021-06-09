<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLocationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('locations')) {
            return;
        }
        Schema::create('locations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 50)->nullable();
            $table->string('location_main', 200)->default('');
            $table->string('location_sub', 200)->default('');
            $table->string('flagpic', 50)->nullable();
            $table->string('start_ip', 20)->default('');
            $table->string('end_ip', 20)->default('');
            $table->unsignedInteger('theory_upspeed')->default(10);
            $table->unsignedInteger('practical_upspeed')->default(10);
            $table->unsignedInteger('theory_downspeed')->default(10);
            $table->unsignedInteger('practical_downspeed')->default(10);
            $table->unsignedInteger('hit')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('locations');
    }
}
