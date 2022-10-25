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
        Schema::table('torrents_custom_fields', function (Blueprint $table) {
            $table->text('display')->nullable(true)->after('help');
            $table->integer('priority')->default(0)->after('display');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('torrents_custom_fields', function (Blueprint $table) {
            $table->dropColumn('display', 'priority');
        });
    }
};
