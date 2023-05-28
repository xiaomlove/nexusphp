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
        Schema::table('agent_allowed_family', function (Blueprint $table) {
            $table->string("comment")->nullable(true)->default(null)->change();
        });
        Schema::table('agent_allowed_exception', function (Blueprint $table) {
            $table->string("comment")->nullable(true)->default(null)->change();
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
