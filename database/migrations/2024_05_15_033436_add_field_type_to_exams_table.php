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
        Schema::table('exams', function (Blueprint $table) {
            $table->integer("type")->default(\App\Models\Exam::TYPE_EXAM);
            $table->integer("success_reward_bonus")->default(0);
            $table->integer("fail_deduct_bonus")->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(["type", "success_reward_bonus", "fail_deduct_bonus"]);
        });
    }
};
