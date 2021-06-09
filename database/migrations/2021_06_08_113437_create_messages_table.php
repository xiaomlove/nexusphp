<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('messages')) {
            return;
        }
        Schema::create('messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('sender')->default(0)->index('sender');
            $table->unsignedMediumInteger('receiver')->default(0)->index('receiver');
            $table->dateTime('added')->nullable();
            $table->string('subject', 128)->default('');
            $table->text('msg')->nullable();
            $table->enum('unread', ['yes', 'no'])->default('yes');
            $table->smallInteger('location')->default(1);
            $table->enum('saved', ['no', 'yes'])->default('no');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('messages');
    }
}
