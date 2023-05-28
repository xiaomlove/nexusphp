<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOverforumsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('overforums')) {
            return;
        }
        Schema::create('overforums', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 60)->default('');
            $table->string('description')->default('');
            $table->unsignedTinyInteger('minclassview')->default(0);
            $table->unsignedTinyInteger('sort')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('overforums');
    }
}
