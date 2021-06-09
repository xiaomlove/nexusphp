<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentAllowedFamilyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('agent_allowed_family')) {
            return;
        }
        Schema::create('agent_allowed_family', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('family', 50)->default('');
            $table->string('start_name', 100)->default('');
            $table->string('peer_id_pattern', 200)->default('');
            $table->unsignedTinyInteger('peer_id_match_num')->default(0);
            $table->enum('peer_id_matchtype', ['dec', 'hex'])->default('dec');
            $table->string('peer_id_start', 20)->default('');
            $table->string('agent_pattern', 200)->default('');
            $table->unsignedTinyInteger('agent_match_num')->default(0);
            $table->enum('agent_matchtype', ['dec', 'hex'])->default('dec');
            $table->string('agent_start', 100)->default('');
            $table->enum('exception', ['yes', 'no'])->default('no');
            $table->enum('allowhttps', ['yes', 'no'])->default('no');
            $table->string('comment', 200)->default('');
            $table->unsignedMediumInteger('hits')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('agent_allowed_family');
    }
}
