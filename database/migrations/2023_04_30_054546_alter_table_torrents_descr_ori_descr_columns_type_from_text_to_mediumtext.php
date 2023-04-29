<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTableTorrentsDescrOriDescrColumnsTypeFromTextToMediumtext extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('torrents', function (Blueprint $table) {
            $table->mediumText('descr')->change();
            $table->mediumText('ori_descr')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('text_to_mediumtext', function (Blueprint $table) {
            $table->text('descr')->change();
            $table->text('ori_descr')->change();
        });
    }
};
