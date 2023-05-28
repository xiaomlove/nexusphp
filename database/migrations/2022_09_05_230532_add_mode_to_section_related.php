<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private static array $tables = [
        'sources', 'media', 'standards', 'codecs', 'audiocodecs', 'teams', 'processings', 'secondicons'
    ];
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach (self::$tables as $table) {
            if (!\Nexus\Database\NexusDB::hasColumn($table, 'mode')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->integer('mode')->default(0);
                });
            }
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach (self::$tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('mode');
            });
        }
    }
};
