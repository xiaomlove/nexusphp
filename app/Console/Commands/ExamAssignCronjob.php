<?php

namespace App\Console\Commands;

use App\Repositories\ExamRepository;
use Illuminate\Console\Command;

class ExamAssignCronjob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exam:assign_cronjob';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign exam cronjob';

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
        $result = $examRep->cronjonAssign();
        $log = sprintf('[%s], %s, result: %s', nexus()->getRequestId(), __METHOD__, var_export($result, true));
        $this->info($log);
        do_log($log);
        return 0;
    }
}
