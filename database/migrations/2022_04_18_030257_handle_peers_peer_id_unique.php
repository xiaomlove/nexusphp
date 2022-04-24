<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        $tableName = 'peers';
        $result = DB::select('show index from ' . $tableName);
        $indexToDrop = [];
        foreach ($result as $item) {
            if (in_array($item->Column_name, ['torrent', 'peer_id'])) {
                $indexToDrop[$item->Key_name] = "drop index " . $item->Key_name;
            }
        }
        if (!empty($indexToDrop)) {
            $sql = sprintf("alter table %s %s", $tableName, implode(', ', $indexToDrop));
            DB::statement($sql);
        }

        $sql = "alter table peers add index idx_torrent_peer(`torrent`, `peer_id`(20))";
        DB::statement($sql);

        $sql = "alter table peers add index idx_peer(`peer_id`(20))";
        DB::statement($sql);

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
