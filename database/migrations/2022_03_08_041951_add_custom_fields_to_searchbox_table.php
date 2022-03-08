<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCustomFieldsToSearchboxTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('searchbox', 'custom_fields')) {
            return;
        }
        Schema::table('searchbox', function (Blueprint $table) {
            $table->text('custom_fields')->nullable();
            $table->string('custom_fields_display_name')->nullable();
            $table->text('custom_fields_display')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('searchbox', function (Blueprint $table) {
            $table->dropColumn(['custom_fields', 'custom_fields_display_name', 'custom_fields_display']);
        });
    }
}
