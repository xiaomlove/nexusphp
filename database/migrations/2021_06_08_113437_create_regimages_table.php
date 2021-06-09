<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRegimagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('regimages')) {
            return;
        }
        Schema::create('regimages', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->string('imagehash', 32)->default('');
            $table->string('imagestring', 8)->default('');
            $table->unsignedInteger('dateline')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('regimages');
    }
}
