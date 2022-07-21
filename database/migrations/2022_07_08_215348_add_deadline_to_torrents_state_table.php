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
        if (Schema::hasColumn('torrents_state', 'deadline')) {
            return;
        }
        Schema::table('torrents_state', function (Blueprint $table) {
            $table->id();
            $table->dateTime('deadline')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('torrents_state', function (Blueprint $table) {
            $table->dropColumn(['id', 'deadline']);
        });
    }
};
