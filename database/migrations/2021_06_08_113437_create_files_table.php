<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('files')) {
            return;
        }
        Schema::create('files', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('torrent')->default(0)->index('torrent');
            $table->string('filename')->default('');
            $table->unsignedBigInteger('size')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('files');
    }
}
