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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal("seedbonus", 20, 1)->change();
        });
        Schema::table('bonus_logs', function (Blueprint $table) {
            $table->decimal("old_total_value", 20, 1)->change();
            $table->decimal("value", 20, 1)->change();
            $table->decimal("new_total_value", 20, 1)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
