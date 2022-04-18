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

        Schema::table($tableName, function (Blueprint $table) {
            $table->unique(['torrent', 'peer_id', 'ip']);
        });
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
