<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExamProgressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('exam_progress')) {
            return;
        }
        Schema::create('exam_progress', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('exam_user_id')->index();
            $table->integer('exam_id')->index();
            $table->integer('uid')->index();
            $table->integer('torrent_id');
            $table->integer('index');
            $table->bigInteger('value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exam_progress');
    }
}
