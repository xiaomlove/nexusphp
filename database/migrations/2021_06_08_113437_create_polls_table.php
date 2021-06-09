<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePollsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('polls')) {
            return;
        }
        Schema::create('polls', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->dateTime('added')->nullable();
            $table->string('question')->default('');
            $table->string('option0', 40)->default('');
            $table->string('option1', 40)->default('');
            $table->string('option2', 40)->default('');
            $table->string('option3', 40)->default('');
            $table->string('option4', 40)->default('');
            $table->string('option5', 40)->default('');
            $table->string('option6', 40)->default('');
            $table->string('option7', 40)->default('');
            $table->string('option8', 40)->default('');
            $table->string('option9', 40)->default('');
            $table->string('option10', 40)->default('');
            $table->string('option11', 40)->default('');
            $table->string('option12', 40)->default('');
            $table->string('option13', 40)->default('');
            $table->string('option14', 40)->default('');
            $table->string('option15', 40)->default('');
            $table->string('option16', 40)->default('');
            $table->string('option17', 40)->default('');
            $table->string('option18', 40)->default('');
            $table->string('option19', 40)->default('');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('polls');
    }
}
