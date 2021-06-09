<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMagicTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('magic')) {
            return;
        }
        Schema::create('magic', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('torrentid')->default(0)->index('idx_torrentid');
            $table->integer('userid')->default(0)->index('idx_userid');
            $table->integer('value')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('magic');
    }
}
