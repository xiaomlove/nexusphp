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
            $table->integer('uid');
            $table->integer('torrent_id');
            $table->integer('snatched_id')->unique();
            $table->integer('status')->default(1);
            $table->string('comment')->default('');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['uid', 'torrent_id']);
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
