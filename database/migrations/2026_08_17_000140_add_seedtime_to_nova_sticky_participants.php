<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nova_sticky_promotion_participants', function (Blueprint $table) {
            $table->unsignedBigInteger('baseline_seedtime')->default(0)->after('baseline_uploaded');
        });
    }

    public function down(): void
    {
        Schema::table('nova_sticky_promotion_participants', function (Blueprint $table) {
            $table->dropColumn('baseline_seedtime');
        });
    }
};
