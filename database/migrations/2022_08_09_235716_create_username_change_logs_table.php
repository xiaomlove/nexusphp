<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('username_change_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('uid');
            $table->string('operator');
            $table->integer('change_type')->nullable(false)->default(0);
            $table->string('username_old');
            $table->string('username_new');
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
        Schema::dropIfExists('username_change_logs');
    }
};
