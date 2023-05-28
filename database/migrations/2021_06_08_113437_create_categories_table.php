<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('categories')) {
            return;
        }
        Schema::create('categories', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedTinyInteger('mode')->default(1);
            $table->string('class_name')->default('');
            $table->string('name', 30)->default('');
            $table->string('image')->default('');
            $table->unsignedSmallInteger('sort_index')->default(0);
            $table->integer('icon_id')->default(0);
            $table->index(['mode', 'sort_index'], 'mode_sort');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('categories');
    }
}
