<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTorrentsCustomFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('torrents_custom_fields')) {
            return;
        }
        Schema::create('torrents_custom_fields', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name')->default('');
            $table->string('label')->default('');
            $table->enum('type', ['text', 'textarea', 'select', 'radio', 'checkbox', 'image']);
            $table->tinyInteger('required')->default(0);
            $table->integer('is_single_row')->default(0);
            $table->text('options')->nullable();
            $table->text('help')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('torrents_custom_fields');
    }
}
