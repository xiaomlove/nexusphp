<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('torrents', function (Blueprint $table) {
            $table->binary('info_hash_v2', 20)->nullable()->after('info_hash');
        });
    }

    public function down(): void
    {
        Schema::table('torrents', function (Blueprint $table) {
            //
        });
    }
};
