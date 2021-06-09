<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIplogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('iplog')) {
            return;
        }
        Schema::create('iplog', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('ip', 64)->default('');
            $table->unsignedMediumInteger('userid')->default(0)->index('userid');
            $table->dateTime('access')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('iplog');
    }
}
