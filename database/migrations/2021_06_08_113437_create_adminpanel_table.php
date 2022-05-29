<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminpanelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('adminpanel')) {
            return;
        }
        Schema::create('adminpanel', function (Blueprint $table) {
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
        Schema::dropIfExists('adminpanel');
    }
}
