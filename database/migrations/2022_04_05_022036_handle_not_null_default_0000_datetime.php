<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tableFields = [
            'comments' => ['editdate'],
            'invites' => ['time_invited'],
            'offers' => ['allowedtime'],
            'peers' => ['last_action', 'prev_action'],
            'posts' => ['editdate'],
            'snatched' => ['last_action', 'completedat'],
            'torrents' => ['last_action', 'promotion_until', 'picktime', 'last_reseed'],
            'users' => [
                'last_login', 'last_access', 'last_home', 'last_offer', 'forum_access', 'last_staffmsg',
                'last_pm', 'last_comment', 'last_post', 'donoruntil', 'warneduntil', 'noaduntil', 'vip_until',
                'leechwarnuntil', 'lastwarned',
            ],
        ];

        foreach ($tableFields as $table => $fields) {
            $columnInfo = \Nexus\Database\NexusDB::getMysqlColumnInfo($table);
            $modifies = [];
            foreach ($fields as $field) {
                if (isset($columnInfo[$field]) && $columnInfo[$field]['COLUMN_DEFAULT'] == '0000-00-00 00:00:00') {
                    $modifies[] = sprintf('modify `%s` datetime default null', $field);
                }
            }
            if (!empty($modifies)) {
                $sql = sprintf("alter table `%s` %s", $table, implode(', ', $modifies));
                \Illuminate\Support\Facades\DB::statement($sql);
            }
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
