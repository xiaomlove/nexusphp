<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('users')) {
            return;
        }
        Schema::create('users', function (Blueprint $table) {
            $table->id('id')->startingValue(10001);
            $table->string('username', 40)->default('')->unique('username');
            $table->string('passhash', 32)->default('');
            $table->binary('secret');
            $table->string('email', 80)->default('');
            $table->enum('status', ['pending', 'confirmed'])->default('pending');
            $table->dateTime('added')->nullable();
            $table->dateTime('last_login')->nullable();
            $table->dateTime('last_access')->nullable()->index('last_access');
            $table->dateTime('last_home')->nullable();
            $table->dateTime('last_offer')->nullable();
            $table->dateTime('forum_access')->nullable();
            $table->dateTime('last_staffmsg')->nullable();
            $table->dateTime('last_pm')->nullable();
            $table->dateTime('last_comment')->nullable();
            $table->dateTime('last_post')->nullable();
            $table->unsignedInteger('last_browse')->default(0);
            $table->unsignedInteger('last_music')->default(0);
            $table->unsignedInteger('last_catchup')->default(0);
            $table->binary('editsecret');
            $table->enum('privacy', ['strong', 'normal', 'low'])->default('normal');
            $table->unsignedTinyInteger('stylesheet')->default(1);
            $table->unsignedTinyInteger('caticon')->default(1);
            $table->enum('fontsize', ['small', 'medium', 'large'])->default('medium');
            $table->text('info')->nullable();
            $table->enum('acceptpms', ['yes', 'friends', 'no'])->default('yes');
            $table->enum('commentpm', ['yes', 'no'])->default('yes');
            $table->string('ip', 64)->default('')->index('ip');
            $table->unsignedTinyInteger('class')->default(1)->index('class');
            $table->tinyInteger('max_class_once')->default(1);
            $table->string('avatar')->default('');
            $table->unsignedBigInteger('uploaded')->default(0)->index('uploaded');
            $table->unsignedBigInteger('downloaded')->default(0)->index('downloaded');
            $table->unsignedBigInteger('seedtime')->default(0);
            $table->unsignedBigInteger('leechtime')->default(0);
            $table->string('title', 30)->default('');
            $table->unsignedSmallInteger('country')->default(107)->index('country');
            $table->string('notifs', 500)->nullable();
            $table->text('modcomment')->nullable();
            $table->enum('enabled', ['yes', 'no'])->default('yes')->index('enabled');
            $table->enum('avatars', ['yes', 'no'])->default('yes');
            $table->enum('donor', ['yes', 'no'])->default('no');
            $table->decimal('donated')->default(0.00);
            $table->decimal('donated_cny')->default(0.00);
            $table->dateTime('donoruntil')->nullable();
            $table->enum('warned', ['yes', 'no'])->default('no')->index('warned');
            $table->dateTime('warneduntil')->nullable();
            $table->enum('noad', ['yes', 'no'])->default('no');
            $table->dateTime('noaduntil')->nullable();
            $table->unsignedTinyInteger('torrentsperpage')->default(0);
            $table->unsignedTinyInteger('topicsperpage')->default(0);
            $table->unsignedTinyInteger('postsperpage')->default(0);
            $table->enum('clicktopic', ['firstpage', 'lastpage'])->default('firstpage');
            $table->enum('deletepms', ['yes', 'no'])->default('yes');
            $table->enum('savepms', ['yes', 'no'])->default('no');
            $table->enum('showhot', ['yes', 'no'])->default('yes');
            $table->enum('showclassic', ['yes', 'no'])->default('yes');
            $table->enum('support', ['yes', 'no'])->default('no');
            $table->enum('picker', ['yes', 'no'])->default('no');
            $table->string('stafffor')->default('');
            $table->string('supportfor')->default('');
            $table->string('pickfor')->default('');
            $table->string('supportlang', 50)->default('');
            $table->string('passkey', 32)->default('')->index('passkey');
            $table->string('promotion_link', 32)->nullable();
            $table->enum('uploadpos', ['yes', 'no'])->default('yes');
            $table->enum('forumpost', ['yes', 'no'])->default('yes');
            $table->enum('downloadpos', ['yes', 'no'])->default('yes');
            $table->unsignedTinyInteger('clientselect')->default(0);
            $table->enum('signatures', ['yes', 'no'])->default('yes');
            $table->string('signature', 800)->default('');
            $table->unsignedSmallInteger('lang')->default(6);
            $table->smallInteger('cheat')->default(0)->index('cheat');
            $table->unsignedInteger('download')->default(0);
            $table->unsignedInteger('upload')->default(0);
            $table->unsignedTinyInteger('isp')->default(0);
            $table->unsignedSmallInteger('invites')->default(0);
            $table->unsignedMediumInteger('invited_by')->default(0);
            $table->enum('gender', ['Male', 'Female', 'N/A'])->default('N/A');
            $table->enum('vip_added', ['yes', 'no'])->default('no');
            $table->dateTime('vip_until')->nullable();
            $table->decimal('seedbonus', 10, 1)->default(0.0);
            $table->decimal('charity', 10, 1)->default(0.0);
            $table->text('bonuscomment')->nullable();
            $table->enum('parked', ['yes', 'no'])->default('no');
            $table->enum('leechwarn', ['yes', 'no'])->default('no');
            $table->dateTime('leechwarnuntil')->nullable();
            $table->dateTime('lastwarned')->nullable();
            $table->unsignedTinyInteger('timeswarned')->default(0);
            $table->unsignedMediumInteger('warnedby')->default(0);
            $table->unsignedSmallInteger('sbnum')->default(70);
            $table->unsignedSmallInteger('sbrefresh')->default(120);
            $table->enum('hidehb', ['yes', 'no'])->nullable()->default('no');
            $table->enum('showimdb', ['yes', 'no'])->nullable()->default('yes');
            $table->enum('showdescription', ['yes', 'no'])->nullable()->default('yes');
            $table->enum('showcomment', ['yes', 'no'])->nullable()->default('yes');
            $table->enum('showclienterror', ['yes', 'no'])->default('no');
            $table->boolean('showdlnotice')->default(1);
            $table->enum('tooltip', ['minorimdb', 'medianimdb', 'off'])->default('off');
            $table->enum('shownfo', ['yes', 'no'])->nullable()->default('yes');
            $table->enum('timetype', ['timeadded', 'timealive'])->nullable()->default('timealive');
            $table->enum('appendsticky', ['yes', 'no'])->nullable()->default('yes');
            $table->enum('appendnew', ['yes', 'no'])->nullable()->default('yes');
            $table->enum('appendpromotion', ['highlight', 'word', 'icon', 'off'])->nullable()->default('icon');
            $table->enum('appendpicked', ['yes', 'no'])->nullable()->default('yes');
            $table->enum('dlicon', ['yes', 'no'])->nullable()->default('yes');
            $table->enum('bmicon', ['yes', 'no'])->nullable()->default('yes');
            $table->enum('showsmalldescr', ['yes', 'no'])->default('yes');
            $table->enum('showcomnum', ['yes', 'no'])->nullable()->default('yes');
            $table->enum('showlastcom', ['yes', 'no'])->nullable()->default('no');
            $table->enum('showlastpost', ['yes', 'no'])->default('no');
            $table->unsignedTinyInteger('pmnum')->default(10);
            $table->unsignedSmallInteger('school')->default(35);
            $table->enum('showfb', ['yes', 'no'])->default('yes');
            $table->string('page')->nullable()->default('');
            $table->index(['status', 'added'], 'status_added');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
