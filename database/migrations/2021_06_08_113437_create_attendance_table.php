<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendanceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('attendance')) {
            return;
        }
        Schema::create('attendance', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('uid')->default(0)->index('idx_uid');
            $table->dateTime('added')->index();
            $table->unsignedInteger('points')->default(0);
            $table->unsignedInteger('days')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attendance');
    }
}
