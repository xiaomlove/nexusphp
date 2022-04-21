<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePeersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('peers')) {
            return;
        }
        Schema::create('peers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('torrent')->default(0);
            $table->char('peer_id', 20)->charset('binary')->index();
            $table->string('ip', 64)->default('');
            $table->unsignedSmallInteger('port')->default(0);
            $table->unsignedBigInteger('uploaded')->default(0);
            $table->unsignedBigInteger('downloaded')->default(0);
            $table->unsignedBigInteger('to_go')->default(0);
            $table->enum('seeder', ['yes', 'no'])->default('no');
            $table->dateTime('started')->nullable();
            $table->dateTime('last_action')->nullable();
            $table->dateTime('prev_action')->nullable();
            $table->enum('connectable', ['yes', 'no'])->default('yes');
            $table->unsignedMediumInteger('userid')->default(0)->index();
            $table->string('agent', 60)->default('');
            $table->unsignedInteger('finishedat')->default(0);
            $table->unsignedBigInteger('downloadoffset')->default(0);
            $table->unsignedBigInteger('uploadoffset')->default(0);
            $table->char('passkey', 32)->default('');
            $table->index(['torrent', 'peer_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('peers');
    }
}
