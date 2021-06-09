<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExamUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('exam_users')) {
            return;
        }
        Schema::create('exam_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('uid')->index();
            $table->integer('exam_id')->index();
            $table->integer('status')->default(0);
            $table->dateTime('begin')->nullable();
            $table->dateTime('end')->nullable();
            $table->text('progress')->nullable();
            $table->tinyInteger('is_done')->default(0);
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
        Schema::dropIfExists('exam_users');
    }
}
