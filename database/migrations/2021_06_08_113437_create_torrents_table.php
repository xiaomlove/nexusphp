<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTorrentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('torrents')) {
            return;
        }
        Schema::create('torrents', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->string('name')->default('')->index('name');
            $table->string('filename')->default('');
            $table->string('save_as')->default('');
            $table->text('descr')->nullable();
            $table->string('small_descr')->default('');
            $table->text('ori_descr')->nullable();
            $table->unsignedSmallInteger('category')->default(0);
            $table->unsignedTinyInteger('source')->default(0);
            $table->unsignedTinyInteger('medium')->default(0);
            $table->unsignedTinyInteger('codec')->default(0);
            $table->unsignedTinyInteger('standard')->default(0);
            $table->unsignedTinyInteger('processing')->default(0);
            $table->unsignedTinyInteger('team')->default(0);
            $table->unsignedTinyInteger('audiocodec')->default(0);
            $table->unsignedBigInteger('size')->default(0);
            $table->dateTime('added')->nullable();
            $table->enum('type', ['single', 'multi'])->default('single');
            $table->unsignedSmallInteger('numfiles')->default(0);
            $table->unsignedMediumInteger('comments')->default(0);
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedMediumInteger('times_completed')->default(0);
            $table->unsignedMediumInteger('leechers')->default(0);
            $table->unsignedMediumInteger('seeders')->default(0);
            $table->dateTime('last_action')->nullable();
            $table->enum('visible', ['yes', 'no'])->default('yes');
            $table->enum('banned', ['yes', 'no'])->default('no');
            $table->unsignedMediumInteger('owner')->default(0)->index('owner');
            $table->binary('nfo')->nullable();
            $table->unsignedTinyInteger('sp_state')->default(1);
            $table->unsignedTinyInteger('promotion_time_type')->default(0);
            $table->dateTime('promotion_until')->nullable();
            $table->enum('anonymous', ['yes', 'no'])->default('no');
            $table->unsignedInteger('url')->nullable()->index('url');
            $table->string('pos_state', 32)->default('normal');
            $table->unsignedTinyInteger('cache_stamp')->default(0);
            $table->enum('picktype', ['hot', 'classic', 'recommended', 'normal'])->default('normal');
            $table->dateTime('picktime')->nullable();
            $table->dateTime('last_reseed')->nullable();
            $table->mediumText('pt_gen')->nullable();
//            $table->integer('tags')->default(0);
            $table->text('technical_info')->nullable();
            $table->index(['visible', 'pos_state', 'id'], 'visible_pos_id');
            $table->index(['category', 'visible', 'banned'], 'category_visible_banned');
            $table->index(['visible', 'banned', 'pos_state', 'id'], 'visible_banned_pos_id');
        });
        $sql = 'alter table torrents add column `info_hash` binary(20) NOT NULL after id, add unique info_hash(`info_hash`)';
        \Illuminate\Support\Facades\DB::statement($sql);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('torrents');
    }
}
