<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateThanksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('thanks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('torrentid')->default(0);
            $table->unsignedMediumInteger('userid')->default(0);
            $table->unique(['torrentid', 'id'], 'torrentid_id');
            $table->index(['torrentid', 'userid'], 'torrentid_userid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('thanks');
    }
}
