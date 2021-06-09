<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTorrentSecretsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('torrent_secrets')) {
            return;
        }
        Schema::create('torrent_secrets', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('uid')->index('idx_uid');
            $table->integer('torrent_id')->default(0)->index('idx_torrent_id');
            $table->string('secret');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('torrent_secrets');
    }
}
