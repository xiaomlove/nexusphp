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
            $table->integer('display_on_medal_page')->default(1)->after('price');
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
            $table->dropColumn('display_on_medal_page');
        });
    }
};
