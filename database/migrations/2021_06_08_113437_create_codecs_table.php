<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCodecsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('codecs')) {
            return;
        }
        Schema::create('codecs', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name', 30)->default('');
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
        Schema::dropIfExists('codecs');
    }
}
