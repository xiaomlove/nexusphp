<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('bans')) {
            return;
        }
        Schema::create('bans', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->dateTime('added')->nullable();
            $table->unsignedMediumInteger('addedby')->default(0);
            $table->string('comment')->default('');
            $table->bigInteger('first')->default(0);
            $table->bigInteger('last')->default(0);
            $table->index(['first', 'last'], 'first_last');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bans');
    }
}
