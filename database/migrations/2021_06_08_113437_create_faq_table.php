<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFaqTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('faq')) {
            return;
        }
        Schema::create('faq', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('link_id')->default(0);
            $table->unsignedSmallInteger('lang_id')->default(6);
            $table->enum('type', ['categ', 'item'])->default('item');
            $table->text('question');
            $table->text('answer');
            $table->unsignedTinyInteger('flag')->default(1);
            $table->unsignedSmallInteger('categ')->default(0);
            $table->unsignedSmallInteger('order')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('faq');
    }
}
