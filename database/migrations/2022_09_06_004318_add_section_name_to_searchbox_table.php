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
        if (!\Nexus\Database\NexusDB::hasColumn('searchbox', 'section_name')) {
            Schema::table('searchbox', function (Blueprint $table) {
                $table->json('section_name')->after('name')->nullable(true);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('searchbox', function (Blueprint $table) {
            $table->dropColumn('section_name');
        });
    }
};
