<?php

namespace App\Console\Commands;

use App\Repositories\ExamRepository;
use Illuminate\Console\Command;

class ExamCheckoutCronjob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exam:checkout_cronjob {--ignore-time-range}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checkout exam cronjob, options: --ignore-time-range';

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
        $examRep = new ExamRepository();
        $ignoreTimeRange = $this->option('ignore-time-range');
        $this->info('ignore-time-range: ' . var_export($ignoreTimeRange, true));
        $result = $examRep->cronjobCheckout($ignoreTimeRange);
        $log = sprintf('[%s], %s, result: %s', nexus()->getRequestId(), __METHOD__, var_export($result, true));
        $this->info($log);
        do_log($log);
        return 0;
    }
}
