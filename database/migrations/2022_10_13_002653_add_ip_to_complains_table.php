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
        Schema::table('complains', function (Blueprint $table) {
            $table->string('ip')->nullable(true);
        });
        Schema::table('complain_replies', function (Blueprint $table) {
            $table->string('ip')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('complains', function (Blueprint $table) {
            $table->dropColumn('ip');
        });
        Schema::table('complain_replies', function (Blueprint $table) {
            $table->dropColumn('ip');
        });
    }
};
