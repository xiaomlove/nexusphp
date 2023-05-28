<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStaffmessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('staffmessages')) {
            return;
        }
        Schema::create('staffmessages', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->unsignedMediumInteger('sender')->default(0);
            $table->dateTime('added')->nullable();
            $table->text('msg')->nullable();
            $table->string('subject', 128)->default('');
            $table->unsignedMediumInteger('answeredby')->default(0);
            $table->boolean('answered')->default(0);
            $table->text('answer')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('staffmessages');
    }
}
