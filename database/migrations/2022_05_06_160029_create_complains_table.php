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
        if (Schema::hasTable('complains')) {
            return;
        }
        Schema::create('complains', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->string('email');
            $table->text('body');
            $table->dateTime('added');
            $table->smallInteger('answered')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('complains');
    }
};
