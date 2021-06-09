<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('funds')) {
            return;
        }
        Schema::create('funds', function (Blueprint $table) {
            $table->increments('id');
            $table->decimal('usd')->default(0.00);
            $table->decimal('cny')->default(0.00);
            $table->unsignedMediumInteger('user')->default(0);
            $table->dateTime('added')->nullable();
            $table->string('memo')->default('');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('funds');
    }
}
