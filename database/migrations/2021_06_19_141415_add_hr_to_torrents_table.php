<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHrToTorrentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('torrents', function (Blueprint $table) {
            if (!Schema::hasColumn('torrents', 'hr')) {
                $table->tinyInteger('hr')->default(0);
            }
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
            $table->dropColumn('hr');
        });
    }
}
