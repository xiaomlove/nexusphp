<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTopicsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('topics')) {
            return;
        }
        Schema::create('topics', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->unsignedMediumInteger('userid')->default(0)->index('userid');
            $table->string('subject', 128)->default('')->index('subject');
            $table->enum('locked', ['yes', 'no'])->default('no');
            $table->unsignedSmallInteger('forumid')->default(0);
            $table->unsignedInteger('firstpost')->default(0);
            $table->unsignedInteger('lastpost')->default(0);
            $table->enum('sticky', ['no', 'yes'])->default('no');
            $table->unsignedTinyInteger('hlcolor')->default(0);
            $table->unsignedInteger('views')->default(0);
            $table->index(['forumid', 'lastpost'], 'forumid_lastpost');
            $table->index(['forumid', 'sticky', 'lastpost'], 'forumid_sticky_lastpost');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('topics');
    }
}
