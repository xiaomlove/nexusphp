<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateModpanelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('modpanel')) {
            return;
        }
        Schema::create('modpanel', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->default('');
            $table->string('url')->default('');
            $table->string('info')->default('');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('modpanel');
    }
}
