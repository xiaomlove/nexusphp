<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBlocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('blocks')) {
            return;
        }
        Schema::create('blocks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('userid')->default(0);
            $table->unsignedMediumInteger('blockid')->default(0);
            $table->unique(['userid', 'blockid'], 'userfriend');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('blocks');
    }
}
