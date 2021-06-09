<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttachmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('attachments')) {
            return;
        }
        Schema::create('attachments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('userid')->default(0);
            $table->unsignedSmallInteger('width')->default(0);
            $table->dateTime('added')->nullable();
            $table->string('filename')->default('');
            $table->char('dlkey', 32)->index('idx_delkey');
            $table->string('filetype', 50)->default('');
            $table->unsignedBigInteger('filesize')->default(0);
            $table->string('location')->default('');
            $table->mediumInteger('downloads')->default(0);
            $table->boolean('isimage')->unsigned()->default(0);
            $table->boolean('thumb')->unsigned()->default(0);
            $table->index(['userid', 'id'], 'pid');
            $table->index(['added', 'isimage', 'downloads'], 'dateline');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attachments');
    }
}
