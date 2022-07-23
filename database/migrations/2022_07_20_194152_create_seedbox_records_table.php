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
        Schema::create('seed_box_records', function (Blueprint $table) {
            $table->id();
            $table->integer('type');
            $table->integer('uid');
            $table->integer('status')->default(0);
            $table->string('operator')->nullable();
            $table->integer('bandwidth')->nullable();
            $table->string('ip')->nullable();
            $table->string('ip_begin')->nullable();
            $table->string('ip_end')->nullable();
            $table->string('ip_begin_numeric', 128)->index();
            $table->string('ip_end_numeric', 128)->index();
            $table->integer('version');
            $table->string('comment')->nullable();
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
        Schema::dropIfExists('seed_box_records');
    }
};
