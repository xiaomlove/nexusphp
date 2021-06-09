<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSnatchedTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('snatched')) {
            return;
        }
        Schema::create('snatched', function (Blueprint $table) {
            $table->integer('id', true);
            $table->unsignedMediumInteger('torrentid')->default(0);
            $table->unsignedMediumInteger('userid')->default(0)->index('userid');
            $table->string('ip', 64)->default('');
            $table->unsignedSmallInteger('port')->default(0);
            $table->unsignedBigInteger('uploaded')->default(0);
            $table->unsignedBigInteger('downloaded')->default(0);
            $table->unsignedBigInteger('to_go')->default(0);
            $table->unsignedInteger('seedtime')->default(0);
            $table->unsignedInteger('leechtime')->default(0);
            $table->dateTime('last_action')->nullable();
            $table->dateTime('startdat')->nullable();
            $table->dateTime('completedat')->nullable();
            $table->enum('finished', ['yes', 'no'])->default('no');
            $table->index(['torrentid', 'userid'], 'torrentid_userid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('snatched');
    }
}
