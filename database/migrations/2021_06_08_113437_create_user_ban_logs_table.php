<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserBanLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('user_ban_logs')) {
            return;
        }
        Schema::create('user_ban_logs', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->integer('uid')->default(0)->index('idx_uid');
            $table->string('username')->default('')->index('idx_username');
            $table->integer('operator')->default(0);
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_ban_logs');
    }
}
