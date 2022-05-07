<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('complain_replies')) {
            return;
        }
        Schema::create('complain_replies', function (Blueprint $table) {
            $table->id();
            $table->integer('complain');
            $table->integer('userid')->default(0);
            $table->dateTime('added');
            $table->text('body');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('complain_replies');
    }
};
