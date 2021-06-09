<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTorrentsCustomFieldValuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('torrents_custom_field_values')) {
            return;
        }
        Schema::create('torrents_custom_field_values', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('torrent_id')->default(0)->index('idx_torrent_id');
            $table->integer('custom_field_id')->default(0)->index('idx_field_id');
            $table->mediumText('custom_field_value')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });
        \Illuminate\Support\Facades\DB::statement('alter table torrents_custom_field_values add index(custom_field_value(191))');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('torrents_custom_field_values');
    }
}
