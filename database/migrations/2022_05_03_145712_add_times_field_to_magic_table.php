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
        Schema::table('magic', function (Blueprint $table) {
            if (!Schema::hasColumn('magic', 'created_at')) {
                $table->dateTime('created_at')->useCurrent();
            }
            if (!Schema::hasColumn('magic', 'updated_at')) {
                $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('magic', function (Blueprint $table) {
            $table->dropColumn(['created_at', 'updated_at']);
        });
    }
};
