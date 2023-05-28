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
        Schema::table('invites', function (Blueprint $table) {
            $table->dateTime('expired_at')->nullable(true)->index();
            $table->dateTime('created_at')->useCurrent();
            $table->index(['inviter'], 'idx_inviter');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->dropColumn('expired_at', 'created_at');
            $table->dropIndex('idx_inviter');
        });
    }
};
