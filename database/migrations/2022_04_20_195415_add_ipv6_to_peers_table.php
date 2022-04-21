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
        Schema::table('peers', function (Blueprint $table) {
            if (!Schema::hasColumn('peers', 'ipv4')) {
                $table->string('ipv4', 64)->default('');
            }
            if (!Schema::hasColumn('peers', 'ipv6')) {
                $table->string('ipv6', 64)->default('');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('peers', function (Blueprint $table) {
            $table->dropColumn(['ipv4', 'ipv6']);
        });
    }
};
