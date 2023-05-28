<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFunvotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('funvotes')) {
            return;
        }
        Schema::create('funvotes', function (Blueprint $table) {
            $table->unsignedMediumInteger('funid');
            $table->unsignedMediumInteger('userid');
            $table->dateTime('added')->nullable();
            $table->enum('vote', ['fun', 'dull'])->default('fun');
            $table->primary(['funid', 'userid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('funvotes');
    }
}
