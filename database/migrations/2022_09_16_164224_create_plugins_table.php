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
        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable(true);
            $table->string('package_name')->nullable(false)->unique();
            $table->string('remote_url')->nullable(true);
            $table->string('installed_version')->nullable(true);
            $table->text('description')->nullable(true);
            $table->integer('status')->default(-1);
            $table->text('status_result')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('plugins');
    }
};
