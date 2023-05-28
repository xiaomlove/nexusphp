<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentAllowedExceptionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('agent_allowed_exception')) {
            return;
        }
        Schema::create('agent_allowed_exception', function (Blueprint $table) {
            $table->unsignedTinyInteger('family_id')->default(0)->index('family_id');
            $table->string('name', 100)->default('');
            $table->string('peer_id', 20)->default('');
            $table->string('agent', 100)->default('');
            $table->string('comment', 200)->default('');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('agent_allowed_exception');
    }
}
