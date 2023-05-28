<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('requests')) {
            return;
        }
        Schema::create('requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('userid')->default(0)->index('userid');
            $table->string('request', 225)->default('');
            $table->text('descr');
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('cat')->default(0);
            $table->unsignedInteger('filledby')->default(0);
            $table->unsignedInteger('torrentid')->default(0);
            $table->enum('finish', ['yes', 'no'])->default('no');
            $table->integer('amount')->default(0);
            $table->string('ori_descr')->default('');
            $table->integer('ori_amount')->default(0);
            $table->dateTime('added')->nullable();
            $table->index(['finish', 'userid'], 'finish, userid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('requests');
    }
}
