<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('reports')) {
            return;
        }
        Schema::create('reports', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->unsignedMediumInteger('addedby')->default(0);
            $table->dateTime('added')->nullable();
            $table->unsignedInteger('reportid')->default(0);
            $table->enum('type', ['torrent', 'user', 'offer', 'request', 'post', 'comment', 'subtitle'])->default('torrent');
            $table->string('reason')->default('');
            $table->unsignedMediumInteger('dealtby')->default(0);
            $table->boolean('dealtwith')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reports');
    }
}
