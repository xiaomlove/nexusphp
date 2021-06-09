<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCheatersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('cheaters')) {
            return;
        }
        Schema::create('cheaters', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->dateTime('added')->nullable();
            $table->unsignedMediumInteger('userid')->default(0);
            $table->unsignedMediumInteger('torrentid')->default(0);
            $table->unsignedBigInteger('uploaded')->default(0);
            $table->unsignedBigInteger('downloaded')->default(0);
            $table->unsignedMediumInteger('anctime')->default(0);
            $table->unsignedMediumInteger('seeders')->default(0);
            $table->unsignedMediumInteger('leechers')->default(0);
            $table->unsignedTinyInteger('hit')->default(0);
            $table->unsignedMediumInteger('dealtby')->default(0);
            $table->boolean('dealtwith')->default(0);
            $table->string('comment')->default('');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cheaters');
    }
}
