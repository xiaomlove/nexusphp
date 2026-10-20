<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 创建贷款表
        Schema::create('bank_loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->decimal('amount', 15, 2)->comment('贷款金额');
            $table->decimal('interest_rate', 8, 6)->comment('日利率');
            $table->integer('term_days')->comment('贷款期限（天）');
            $table->decimal('remaining_amount', 15, 2)->comment('剩余欠款');
            $table->datetime('due_date')->comment('到期日期');
            $table->enum('status', ['active', 'paid', 'overdue', 'defaulted'])->default('active')->comment('状态');
            $table->datetime('paid_at')->nullable()->comment('还款日期');
            $table->date('last_interest_date')->nullable()->comment('最后计息日期');
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['status']);
            $table->index(['due_date']);
        });

        // 创建存款表
        Schema::create('bank_deposits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->enum('type', ['demand', 'fixed'])->default('fixed')->comment('存款类型：demand=活期，fixed=定期');
            $table->decimal('amount', 15, 2)->comment('存款金额');
            $table->decimal('interest_rate', 8, 6)->comment('日利率');
            $table->integer('term_days')->nullable()->comment('存款期限（天），活期为null');
            $table->datetime('maturity_date')->nullable()->comment('到期日期，活期为null');
            $table->enum('status', ['active', 'matured', 'withdrawn'])->default('active')->comment('状态');
            $table->datetime('matured_at')->nullable()->comment('到期日期');
            $table->datetime('withdrawn_at')->nullable()->comment('支取日期');
            $table->decimal('penalty', 15, 2)->nullable()->comment('提前支取手续费');
            $table->date('last_interest_date')->nullable()->comment('最后计息日期');
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['type']);
            $table->index(['status']);
            $table->index(['maturity_date']);
        });

        // 创建利息记录表
        Schema::create('bank_interest_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->enum('type', ['loan', 'deposit'])->comment('类型');
            $table->unsignedBigInteger('reference_id')->comment('关联ID（贷款或存款ID）');
            $table->decimal('amount', 15, 2)->comment('利息金额');
            $table->decimal('rate', 8, 6)->comment('利率');
            $table->date('calculation_date')->comment('计息日期');
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['type', 'reference_id']);
            $table->index(['calculation_date']);
        });

        // 创建银行交易记录表
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->enum('type', ['loan', 'repay', 'deposit', 'withdraw', 'interest', 'penalty'])->comment('交易类型');
            $table->decimal('amount', 15, 2)->comment('交易金额');
            $table->decimal('balance_before', 15, 2)->comment('交易前余额');
            $table->decimal('balance_after', 15, 2)->comment('交易后余额');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('关联ID');
            $table->string('description')->comment('交易描述');
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['type']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_interest_records');
        Schema::dropIfExists('bank_deposits');
        Schema::dropIfExists('bank_loans');
    }
};
