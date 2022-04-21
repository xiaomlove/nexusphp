<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TrackerCalculateSeedBonus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracker:calculate_seed_bonus {uid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate user seed bonus.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $uid = $this->argument('uid');
        $result = calculate_seed_bonus($uid);
        $log = sprintf(
            "[%s], %s, uid: %s, result: \n%s",
            nexus()->getRequestId(), __METHOD__, $uid, var_export($result, true)
        );
        $this->info($log);
        do_log($log);
        return 0;
    }
}
