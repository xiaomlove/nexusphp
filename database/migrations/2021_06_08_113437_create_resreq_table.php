<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResreqTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('resreq')) {
            return;
        }
        Schema::create('resreq', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('reqid')->default(0)->index('reqid');
            $table->integer('torrentid')->default(0);
            $table->enum('chosen', ['yes', 'no'])->default('no');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('resreq');
    }
}
