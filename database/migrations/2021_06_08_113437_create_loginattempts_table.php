<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLoginattemptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('loginattempts')) {
            return;
        }
        Schema::create('loginattempts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ip', 64)->default('');
            $table->dateTime('added')->nullable();
            $table->enum('banned', ['yes', 'no'])->default('no');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->enum('type', ['login', 'recover'])->default('login');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('loginattempts');
    }
}
