<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('post_edits')) {
            return;
        }
        Schema::create('post_edits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('postid')->index();
            $table->unsignedBigInteger('editor_userid')->index();
            $table->mediumText('body_before');
            $table->string('subject_before', 255)->nullable();
            $table->timestamp('edited_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_edits');
    }
};
