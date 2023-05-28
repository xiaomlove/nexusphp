<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPtGenTagsTechnicalInfoToTorrentsTable extends Migration
{
    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        Schema::table('torrents', function (Blueprint $table) {
            if (!Schema::hasColumn('torrents', 'pt_gen')) {
                $table->mediumText('pt_gen')->nullable();
            }
//            if (!Schema::hasColumn('torrents', 'tags')) {
//                $table->integer('tags')->default(0);
//            }
            if (!Schema::hasColumn('torrents', 'technical_info')) {
                $table->text('technical_info')->nullable();
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
            $table->dropColumn(['pt_gen', 'tags', 'technical_info']);
        });
    }
}
