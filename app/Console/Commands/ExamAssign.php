<?php

namespace App\Console\Commands;

use App\Repositories\ExamRepository;
use Illuminate\Console\Command;

class ExamAssign extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exam:assign {--uid=} {--exam_id=} {--begin=} {--end=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign exam to user, options: --uid, --exam_id, --begin, --end';

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
        $uid = $this->option('uid');
        $examId = $this->option('exam_id');
        $begin = $this->option('begin');
        $end = $this->option('end');
        $this->info(sprintf('uid: %s, examId: %s, begin: %s, end: %s', $uid, $examId, $begin, $end));
        $result = $examRep->assignToUser($uid, $examId, $begin, $end);
        $log = sprintf('[%s], %s, result: %s', nexus()->getRequestId(), __METHOD__, var_export($result, true));
        $this->info($log);
        do_log($log);
        return 0;
    }
}
