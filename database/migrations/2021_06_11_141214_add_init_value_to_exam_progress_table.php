<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInitValueToExamProgressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('exam_progress', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_progress', 'init_value')) {
                $table->bigInteger('init_value')->default(0)->after('index');
            }
            //@todo open in beta10
//            $table->unique(['exam_user_id', 'index']);
//            $table->dropColumn('torrent_id');
//            $table->dropIndex('exam_progress_exam_user_id_index');
//            $table->dropIndex('exam_progress_created_at_index');
        });
//        \Illuminate\Support\Facades\DB::statement('alter table exam_progress modify created_at timestamp default current_timestamp');
//        \Illuminate\Support\Facades\DB::statement('alter table exam_progress modify updated_at timestamp default current_timestamp on update current_timestamp');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('exam_progress', function (Blueprint $table) {
            $table->dropColumn('init_value');
//            $table->dropUnique('exam_progress_exam_user_id_index_unique');
//            $table->integer('torrent_id')->after('uid');
//            $table->index('exam_user_id');
//            $table->index('created_at');
        });
    }
}
