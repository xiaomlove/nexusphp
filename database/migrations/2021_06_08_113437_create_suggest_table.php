<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSuggestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('suggest')) {
            return;
        }
        Schema::create('suggest', function (Blueprint $table) {
            $table->increments('id');
            $table->string('keywords')->default('')->index('keywords');
            $table->unsignedMediumInteger('userid')->default(0);
            $table->dateTime('adddate')->nullable()->index('adddate');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('suggest');
    }
}
