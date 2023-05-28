<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLanguageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('language')) {
            return;
        }
        Schema::create('language', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('lang_name', 50)->default('');
            $table->string('flagpic')->default('');
            $table->unsignedTinyInteger('sub_lang')->default(1);
            $table->unsignedTinyInteger('rule_lang')->default(0);
            $table->unsignedTinyInteger('site_lang')->default(0);
            $table->string('site_lang_folder')->default('');
            $table->enum('trans_state', ['up-to-date', 'outdate', 'incomplete', 'need-new', 'unavailable'])->default('unavailable');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('language');
    }
}
