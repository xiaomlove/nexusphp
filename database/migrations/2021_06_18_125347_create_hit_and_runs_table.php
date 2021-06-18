<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHitAndRunsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hit_and_runs', function (Blueprint $table) {
            $table->id();
            $table->integer('uid')->index();
            $table->integer('peer_id')->unique();
            $table->integer('torrent_id')->index();
            $table->integer('status')->default(1);
            $table->string('comment')->default('');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hit_and_runs');
    }
}
