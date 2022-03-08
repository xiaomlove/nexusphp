<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdToAgentAllowedExceptionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('agent_allowed_exception', function (Blueprint $table) {
            if (!Schema::hasColumn('agent_allowed_exception', 'id')) {
                $table->increments('id');
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
        Schema::table('agent_allowed_exception', function (Blueprint $table) {
            $table->dropColumn('id');
        });
    }
}
