<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOffervotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('offervotes')) {
            return;
        }
        Schema::create('offervotes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('offerid')->default(0);
            $table->unsignedMediumInteger('userid')->default(0)->index('userid');
            $table->enum('vote', ['yeah', 'against'])->default('yeah');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('offervotes');
    }
}
