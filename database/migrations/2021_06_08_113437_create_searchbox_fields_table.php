<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSearchboxFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('searchbox_fields')) {
            return;
        }
        Schema::create('searchbox_fields', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('searchbox_id');
            $table->string('field_type');
            $table->integer('field_id')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('update_at')->useCurrent();
            $table->unique(['searchbox_id', 'field_type', 'field_id'], 'uniq_searchbox_type_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('searchbox_fields');
    }
}
