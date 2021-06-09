<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvitesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('invites')) {
            return;
        }
        Schema::create('invites', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedMediumInteger('inviter')->default(0);
            $table->string('invitee', 80)->default('');
            $table->char('hash', 32)->index('hash');
            $table->dateTime('time_invited')->nullable();
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
        Schema::dropIfExists('invites');
    }
}
