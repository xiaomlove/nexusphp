<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('medals', function (Blueprint $table) {
            $table->string('medal_type', 100)->default('通用')->after('inventory');
        });
    }

    public function down(): void
    {
        Schema::table('medals', function (Blueprint $table) {
            $table->dropColumn('medal_type');
        });
    }
};
