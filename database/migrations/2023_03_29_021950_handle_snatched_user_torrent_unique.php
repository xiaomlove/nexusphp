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
        $tableName = 'snatched';
        $result = DB::select('show index from ' . $tableName);
        $indexToDrop = [];
        foreach ($result as $item) {
            if (in_array($item->Column_name, ['torrentid', 'userid'])) {
                if ($item->Non_unique == 0) {
                    return;
                }
                $indexToDrop[$item->Key_name] = "drop index " . $item->Key_name;
            }
        }
        if (!empty($indexToDrop)) {
            $sql = sprintf("alter table %s %s", $tableName, implode(', ', $indexToDrop));
            DB::statement($sql);
        }

        $sql = "alter table $tableName add unique unique_torrent_user(`torrentid`, `userid`)";
        DB::statement($sql);

        $sql = "alter table $tableName add index idx_user(`userid`)";
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
