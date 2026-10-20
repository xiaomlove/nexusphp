<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 将 bank_interest_records.type 扩展为包含 'demand'
        try {
            DB::statement("ALTER TABLE bank_interest_records MODIFY COLUMN type ENUM('loan','deposit','demand') NOT NULL COMMENT '类型'");
        } catch (\Throwable $e) {
            // 某些环境可能不是 ENUM，忽略
        }
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE bank_interest_records MODIFY COLUMN type ENUM('loan','deposit') NOT NULL COMMENT '类型'");
        } catch (\Throwable $e) {
            // 忽略回滚失败
        }
    }
};






