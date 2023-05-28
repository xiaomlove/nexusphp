<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMarginPaddingToTagsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->string('padding')->default('1px 2px');
            $table->string('margin')->default('0 4px 0 0');
            $table->string('border_radius')->default(0);
            $table->string('font_size')->default('12px');
            $table->string('font_color')->default('#ffffff');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn(['padding', 'margin', 'font_size', 'font_color', 'border_radius']);
        });
    }
}
