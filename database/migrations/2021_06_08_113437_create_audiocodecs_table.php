<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAudiocodecsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('audiocodecs')) {
            return;
        }
        Schema::create('audiocodecs', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name', 30)->default('');
            $table->string('image')->default('');
            $table->unsignedTinyInteger('sort_index')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('audiocodecs');
    }
}
