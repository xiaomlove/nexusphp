<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdvertisementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('advertisements')) {
            return;
        }
        Schema::create('advertisements', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->boolean('enabled')->default(0);
            $table->enum('type', ['bbcodes', 'xhtml', 'text', 'image', 'flash']);
            $table->enum('position', ['header', 'footer', 'belownav', 'belowsearchbox', 'torrentdetail', 'comment', 'interoverforums', 'forumpost', 'popup']);
            $table->tinyInteger('displayorder')->default(0);
            $table->string('name')->default('');
            $table->text('parameters');
            $table->text('code');
            $table->dateTime('starttime');
            $table->dateTime('endtime');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('advertisements');
    }
}
