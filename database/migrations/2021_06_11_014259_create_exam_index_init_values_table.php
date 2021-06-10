<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExamIndexInitValuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('exam_index_init_values', function (Blueprint $table) {
            $table->id();
            $table->integer('uid')->index();
            $table->integer('exam_user_id');
            $table->integer('exam_id')->index();
            $table->integer('index')->index();
            $table->bigInteger('value');
            $table->timestamps();
            $table->unique(['exam_user_id', 'exam_id', 'index']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exam_index_init_values');
    }
}
