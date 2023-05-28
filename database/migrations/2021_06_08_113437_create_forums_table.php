<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateForumsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('forums')) {
            return;
        }
        Schema::create('forums', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('name', 60)->default('');
            $table->string('description')->default('');
            $table->unsignedTinyInteger('minclassread')->default(0);
            $table->unsignedTinyInteger('minclasswrite')->default(0);
            $table->unsignedInteger('postcount')->default(0);
            $table->unsignedInteger('topiccount')->default(0);
            $table->unsignedTinyInteger('minclasscreate')->default(0);
            $table->unsignedSmallInteger('forid')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('forums');
    }
}
