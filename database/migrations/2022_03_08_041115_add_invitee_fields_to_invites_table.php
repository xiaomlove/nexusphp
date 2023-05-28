<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInviteeFieldsToInvitesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('invites', 'valid')) {
            return;
        }
        Schema::table('invites', function (Blueprint $table) {
            $table->tinyInteger('valid')->default(1);
            $table->integer('invitee_register_uid')->nullable();
            $table->string('invitee_register_email')->nullable();
            $table->string('invitee_register_username')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->dropColumn(['valid', 'invitee_register_uid', 'invitee_register_email', 'invitee_register_username']);
        });
    }
}
