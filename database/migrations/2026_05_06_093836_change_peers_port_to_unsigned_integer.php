<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * On MySQL, peers.port is `smallint unsigned` (0..65535) which accommodates
     * all valid TCP ports. On PostgreSQL, Laravel's `unsignedSmallInteger()`
     * degrades to a signed `smallint` (-32768..32767) because PG has no
     * unsigned types, so peers announcing from ephemeral ports (>= 32768)
     * fail with SQLSTATE[22003] "smallint out of range". Widening to
     * `unsigned integer` (0..4294967295) is a safe superset on both engines
     * and eliminates this PG-specific edge case.
     */
    public function up(): void
    {
        Schema::table('peers', function (Blueprint $table) {
            $table->unsignedInteger('port')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('peers', function (Blueprint $table) {
            $table->unsignedSmallInteger('port')->default(0)->change();
        });
    }
};
