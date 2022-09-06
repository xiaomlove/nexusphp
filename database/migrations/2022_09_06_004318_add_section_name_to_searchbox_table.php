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
        Schema::table('searchbox', function (Blueprint $table) {
            $table->string('section_name')->after('name')->default('');
            $table->integer('is_default')->after('section_name')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('searchbox', function (Blueprint $table) {
            $table->dropColumn('section_name', 'is_default');
        });
    }
};
