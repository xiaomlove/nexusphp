<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAvpsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('avps')) {
            return;
        }
        Schema::create('avps', function (Blueprint $table) {
            $table->string('arg', 20)->default('')->primary();
            $table->text('value_s');
            $table->integer('value_i')->default(0);
            $table->unsignedInteger('value_u')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('avps');
    }
}
