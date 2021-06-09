<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSitelogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('sitelog')) {
            return;
        }
        Schema::create('sitelog', function (Blueprint $table) {
            $table->increments('id');
            $table->dateTime('added')->nullable()->index('added');
            $table->text('txt');
            $table->enum('security_level', ['normal', 'mod'])->default('normal');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sitelog');
    }
}
