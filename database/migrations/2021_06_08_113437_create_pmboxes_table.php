<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePmboxesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('pmboxes')) {
            return;
        }
        Schema::create('pmboxes', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->unsignedMediumInteger('userid')->default(0);
            $table->unsignedTinyInteger('boxnumber')->default(2);
            $table->string('name', 15)->default('');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pmboxes');
    }
}
