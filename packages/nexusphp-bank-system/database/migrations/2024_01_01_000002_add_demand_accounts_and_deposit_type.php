<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // bank_deposits 增加 type 字段（demand|fixed）
        if (Schema::hasTable('bank_deposits') && !Schema::hasColumn('bank_deposits', 'type')) {
            Schema::table('bank_deposits', function (Blueprint $table) {
                $table->enum('type', ['demand', 'fixed'])->default('fixed')->after('user_id')->comment('存款类型');
                $table->index(['type']);
            });
        }

        // 新增活期账户表
        if (!Schema::hasTable('bank_demand_accounts')) {
            Schema::create('bank_demand_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->comment('用户ID');
                $table->decimal('balance', 15, 2)->default(0)->comment('活期账户余额');
                $table->decimal('interest_rate', 8, 6)->default(0)->comment('活期日利率');
                $table->date('last_interest_date')->nullable()->comment('最后计息日期');
                $table->timestamps();

                $table->unique(['user_id']);
                $table->index(['updated_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bank_deposits') && Schema::hasColumn('bank_deposits', 'type')) {
            Schema::table('bank_deposits', function (Blueprint $table) {
                $table->dropIndex(['type']);
                $table->dropColumn('type');
            });
        }

        Schema::dropIfExists('bank_demand_accounts');
    }
};



