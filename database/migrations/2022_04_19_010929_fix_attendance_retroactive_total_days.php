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
        $sql = "update attendance,attendance_logs set attendance.total_days = attendance.total_days + (select count(*) from attendance_logs where uid = attendance.uid and is_retroactive = 1)";
        \Illuminate\Support\Facades\DB::update($sql);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
