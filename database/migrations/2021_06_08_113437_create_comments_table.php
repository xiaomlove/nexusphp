<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('comments')) {
            return;
        }
        Schema::create('comments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('user')->default(0)->index('user');
            $table->unsignedMediumInteger('torrent')->default(0);
            $table->dateTime('added')->nullable();
            $table->text('text')->nullable();
            $table->text('ori_text')->nullable();
            $table->unsignedMediumInteger('editedby')->default(0);
            $table->dateTime('editdate')->nullable();
            $table->unsignedMediumInteger('offer')->default(0);
            $table->integer('request')->default(0);
            $table->enum('anonymous', ['yes', 'no'])->default('no');
            $table->index(['torrent', 'id'], 'torrent_id');
            $table->index(['offer', 'id'], 'offer_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('comments');
    }
}
