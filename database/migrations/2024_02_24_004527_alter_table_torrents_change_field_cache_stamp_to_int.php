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
        $columnInfo = \Nexus\Database\NexusDB::getMysqlColumnInfo("torrents", "cache_stamp");
        if ($columnInfo["DATA_TYPE"] == "int") {
            return;
        }
        Schema::table('torrents', function (Blueprint $table) {
            $table->integer("cache_stamp")->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('torrents', function (Blueprint $table) {
            //
        });
    }
};
