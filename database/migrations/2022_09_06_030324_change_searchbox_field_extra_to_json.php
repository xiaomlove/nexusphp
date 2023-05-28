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
        Schema::table('searchbox', function (Blueprint $table) {
            $table->json('extra')->nullable()->change();
            $table->string('custom_fields_display_name')->nullable(true)->default('')->change();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('class_name')->nullable(true)->default('')->change();
            $table->string('image')->nullable(true)->default('')->change();
        });

        Schema::table('caticons', function (Blueprint $table) {
            $table->string('cssfile')->nullable(true)->default('')->change();
            $table->string('designer')->nullable(true)->default('')->change();
            $table->string('comment')->nullable(true)->default('')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('json', function (Blueprint $table) {
            //
        });
    }
};
