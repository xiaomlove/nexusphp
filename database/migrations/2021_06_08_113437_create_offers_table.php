<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOffersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('offers')) {
            return;
        }
        Schema::create('offers', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->unsignedMediumInteger('userid')->default(0)->index('userid');
            $table->string('name', 225)->default('');
            $table->text('descr')->nullable();
            $table->dateTime('added')->nullable();
            $table->dateTime('allowedtime')->nullable();
            $table->unsignedSmallInteger('yeah')->default(0);
            $table->unsignedSmallInteger('against')->default(0);
            $table->unsignedSmallInteger('category')->default(0);
            $table->unsignedMediumInteger('comments')->default(0);
            $table->enum('allowed', ['allowed', 'pending', 'denied'])->default('pending');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('offers');
    }
}
