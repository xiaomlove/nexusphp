<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChronicleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('chronicle')) {
            return;
        }
        Schema::create('chronicle', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->unsignedMediumInteger('userid')->default(0);
            $table->dateTime('added')->nullable()->index('added');
            $table->text('txt')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('chronicle');
    }
}
