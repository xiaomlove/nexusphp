<?php

namespace App\Console\Commands;

use App\Models\ExamUser;
use App\Repositories\ExamRepository;
use Illuminate\Console\Command;

class ExamUpdateProgress extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exam:update_progress {uid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update exam progress.';

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
        $uid = $this->argument('uid');
        $examRep = new ExamRepository();
        $result = $examRep->updateProgress($uid);
        $this->info(nexus()->getRequestId() . ", result: " . var_export($result, true));
        return 0;
    }
}
