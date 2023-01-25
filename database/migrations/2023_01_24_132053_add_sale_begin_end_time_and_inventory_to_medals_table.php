<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('medals', function (Blueprint $table) {
            $table->dateTime('sale_begin_time')->nullable(true);
            $table->dateTime('sale_end_time')->nullable(true);
            $table->integer('inventory')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('medals', function (Blueprint $table) {
            $table->dropColumn(['sale_begin_time', 'sale_end_time', 'inventory']);
        });
    }
};
