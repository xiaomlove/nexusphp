<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSecondiconsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('secondicons')) {
            return;
        }
        Schema::create('secondicons', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedTinyInteger('source')->default(0);
            $table->unsignedTinyInteger('medium')->default(0);
            $table->unsignedTinyInteger('codec')->default(0);
            $table->unsignedTinyInteger('standard')->default(0);
            $table->unsignedTinyInteger('processing')->default(0);
            $table->unsignedTinyInteger('team')->default(0);
            $table->unsignedTinyInteger('audiocodec')->default(0);
            $table->string('name', 30)->default('');
            $table->string('class_name')->nullable();
            $table->string('image')->default('');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('secondicons');
    }
}
