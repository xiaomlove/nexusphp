<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('topicid')->default(0);
            $table->unsignedMediumInteger('userid')->default(0)->index('userid');
            $table->dateTime('added')->nullable()->index('added');
            $table->text('body')->nullable()->index('body');
            $table->text('ori_body')->nullable();
            $table->unsignedMediumInteger('editedby')->default(0);
            $table->dateTime('editdate')->nullable();
            $table->index(['topicid', 'id'], 'topicid_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('posts');
    }
}
