<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTotalDaysAndTotalPointsToAttendanceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumns('attendance',['total_days', 'total_points'])) {
            return;
        }
        Schema::table('attendance', function (Blueprint $table) {
            $table->integer('total_days')->default(0);
            $table->integer('total_points')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn(['total_days', 'total_points']);
        });
    }
}
