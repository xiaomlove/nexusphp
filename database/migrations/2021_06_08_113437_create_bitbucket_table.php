<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBitbucketTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('bitbucket')) {
            return;
        }
        Schema::create('bitbucket', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('owner')->default(0);
            $table->string('name')->default('');
            $table->dateTime('added')->nullable();
            $table->enum('public', ['0', '1'])->default('0');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bitbucket');
    }
}
