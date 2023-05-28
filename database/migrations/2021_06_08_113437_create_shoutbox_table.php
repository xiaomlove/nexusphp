<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShoutboxTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('shoutbox')) {
            return;
        }
        Schema::create('shoutbox', function (Blueprint $table) {
            $table->integer('id', true);
            $table->unsignedMediumInteger('userid')->default(0);
            $table->unsignedInteger('date')->default(0);
            $table->text('text');
            $table->enum('type', ['sb', 'hb'])->default('sb');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shoutbox');
    }
}
