<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nova_system_official_sticky_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('enabled')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('updated_by')->default(0);
            $table->dateTime('updated_at');
        });
        DB::table('nova_system_official_sticky_settings')->insert([
            'id' => 1,
            'enabled' => true,
            'starts_at' => now(),
            'ends_at' => null,
            'updated_by' => 0,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('nova_system_official_sticky_settings');
    }
};
