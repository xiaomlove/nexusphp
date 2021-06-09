<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStylesheetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('stylesheets')) {
            return;
        }
        Schema::create('stylesheets', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('uri')->default('');
            $table->string('name', 64)->default('');
            $table->text('addicode')->nullable();
            $table->string('designer', 50)->default('');
            $table->string('comment')->default('');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stylesheets');
    }
}
