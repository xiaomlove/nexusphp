<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->select(['id', 'auth_key'])->orderBy('id')->chunkById(500, function ($users) {
            foreach ($users as $user) {
                if ($user->auth_key === null || trim($user->auth_key) === '') {
                    // Compare the old value to avoid overwriting a concurrent successful login.
                    DB::table('users')->where('id', $user->id)->where('auth_key', $user->auth_key)
                        ->update(['auth_key' => bin2hex(random_bytes(32))]);
                }
            }
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('auth_key', 255)->nullable(false)->default(null)->change();
        });
    }

    public function down(): void
    {
        // Never restore compromised signing keys.
        Schema::table('users', function (Blueprint $table) {
            $table->string('auth_key', 255)->nullable(false)->default('')->change();
        });
    }
};
