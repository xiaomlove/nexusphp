<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSearchboxTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('searchbox')) {
            return;
        }
        Schema::create('searchbox', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 30)->nullable();
            $table->boolean('showsubcat')->default(0);
            $table->boolean('showsource')->default(0);
            $table->boolean('showmedium')->default(0);
            $table->boolean('showcodec')->default(0);
            $table->boolean('showstandard')->default(0);
            $table->boolean('showprocessing')->default(0);
            $table->boolean('showteam')->default(0);
            $table->boolean('showaudiocodec')->default(0);
            $table->unsignedSmallInteger('catsperrow')->default(7);
            $table->unsignedSmallInteger('catpadding')->default(25);
            $table->text('custom_fields')->nullable();
            $table->string('custom_fields_display_name')->default('');
            $table->text('custom_fields_display')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('searchbox');
    }
}
