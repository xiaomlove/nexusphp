<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UserResetIdAutoIncrement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:reset_id_auto_increment {--auto_increment=} {--admin=} {--email=} {--password=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset user table PRIMARY KEY auto_increment, options: --auto_increment, --admin, --email, --password';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $options = $this->options();
        $requires = ['auto_increment', 'admin', 'password', 'email'];
        foreach ($requires as $option) {
            if (empty($options[$option])) {
                $this->error("Require --$option");
                return 1;
            }
        }
        $log = "options: " . json_encode($options);
        $this->info($log);

        $tablesToTruncate = [
            'adclicks', 'attachments', 'attendance', 'attendance_logs', 'bitbucket', 'blocks', 'bonus_logs', 'bookmarks', 'cheaters', 'chronicle',
            'claims', 'comments', 'complain_replies', 'complains', 'exam_progress', 'exam_users', 'forummods', 'friends', 'fun', 'funds', 'funvotes',
            'hit_and_runs', 'invites', 'iplog', 'loginattempts', 'lucky_draw_winning_records', 'magic', 'messages', 'offers', 'offervotes', 'peers',
            'pmboxes', 'pollanswers', 'posts', 'prolinkclicks', 'readposts', 'reports', 'requests', 'seed_box_records', 'shoutbox', 'snatched',
            'staffmessages', 'sticky_promotion_appends', 'sticky_promotion_participators', 'sticky_promotions', 'subs', 'suggest', 'thanks', 'topics',
            'torrent_operation_logs', 'torrent_secrets', 'torrents', 'user_ban_logs', 'user_medals', 'user_metas', 'user_permissions', 'user_roles',
            'username_change_logs', 'users',
        ];
        $allTables = DB::select('show tables');
        foreach ($allTables as $tableObj) {
            $tableName = current($tableObj);
            if (in_array($tableName, $tablesToTruncate)) {
                $this->info("truncate table: $tableName ...");
                DB::table($tableName)->truncate();
            }
        }
        $statement = "alter table users auto_increment = " . $options['auto_increment'];
        $this->info($statement);
        $result = DB::statement($statement);

        $userRep = new UserRepository();
        $insert = [
            'username' => $options['admin'],
            'email' => $options['email'],
            'password' => $options['password'],
            'password_confirmation' => $options['password'],
            'class' => User::CLASS_STAFF_LEADER,
            'id' => 1,
        ];
        $userRep->store($insert);

        $log = sprintf('[%s], %s, result: %s', nexus()->getRequestId(), __METHOD__, var_export($result, true));
        $this->info($log);
        do_log($log);
    }
}
