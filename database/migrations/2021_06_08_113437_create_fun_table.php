<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFunTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('fun')) {
            return;
        }
        Schema::create('fun', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->unsignedMediumInteger('userid')->default(0);
            $table->dateTime('added')->nullable();
            $table->text('body')->nullable();
            $table->string('title')->default('');
            $table->enum('status', ['normal', 'dull', 'notfunny', 'funny', 'veryfunny', 'banned'])->default('normal');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fun');
    }
}
