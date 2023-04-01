<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tableName = 'peers';
        $result = DB::select('show index from ' . $tableName);
        $toDropIndex = 'idx_torrent_peer';
        foreach ($result as $item) {
            if ($item->Key_name == $toDropIndex) {
                DB::statement("alter table $tableName drop index $toDropIndex");
                break;
            }
        }
        DB::statement("alter table $tableName add unique unique_torrent_peer_user(`torrent`, `peer_id`, `userid`)");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('peers', function (Blueprint $table) {
            //
        });
    }
};
