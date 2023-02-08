<?php

namespace App\Console\Commands;

use App\Jobs\GenerateTemporaryInvite;
use Illuminate\Console\Command;

class InviteAddTemporary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invite:tmp {uid} {days} {count}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add temporary invite to user, argument: uid(Multiple comma separated), days, count';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $uid = $this->argument('uid');
        $days = $this->argument('days');
        $count = $this->argument('count');
        $log = "uid: $uid, days: $days, count: $count";
        $this->info($log);
        do_log($log);
        $uidArr = preg_split('/[\s,]+/', $uid);
        GenerateTemporaryInvite::dispatch($uidArr, $days, $count);
        return Command::SUCCESS;
    }
}
