<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCaticonsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('caticons')) {
            return;
        }
        Schema::create('caticons', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name', 64)->default('');
            $table->string('folder')->default('');
            $table->string('cssfile')->default('');
            $table->enum('multilang', ['yes', 'no'])->default('no');
            $table->enum('secondicon', ['yes', 'no'])->default('no');
            $table->string('designer', 50)->default('');
            $table->string('comment')->default('');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('caticons');
    }
}
