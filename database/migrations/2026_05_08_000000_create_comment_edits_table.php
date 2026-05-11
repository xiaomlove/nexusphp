<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comment_edits')) {
            return;
        }
        Schema::create('comment_edits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('commentid')->index();
            $table->unsignedBigInteger('editor_userid')->index();
            $table->mediumText('body_before');
            $table->timestamp('edited_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_edits');
    }
};
