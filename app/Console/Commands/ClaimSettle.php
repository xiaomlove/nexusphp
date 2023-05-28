<?php

namespace App\Console\Commands;

use App\Repositories\ClaimRepository;
use Illuminate\Console\Command;

class ClaimSettle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'claim:settle {--uid=} {--force=} {--test=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Claim settle, options: --uid, --force, --test';

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
        $rep = new ClaimRepository();
        $uid = $this->option('uid');
        $force = $this->option('force');
        $test = $this->option('test');
        $this->info(sprintf('uid: %s, force: %s, test: %s', $uid, $force, $test));
        if (!$uid) {
            $result = $rep->settleCronjob();
        } else {
            $result = $rep->settleUser($uid, $force, $test);
        }
        $log = sprintf('[%s], %s, result: %s', nexus()->getRequestId(), __METHOD__, var_export($result, true));
        $this->info($log);
        do_log($log);
        return 0;
    }
}
