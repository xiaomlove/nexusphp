<?php

namespace App\Console\Commands;

use App\Repositories\ToolRepository;
use Illuminate\Console\Command;

class BackupWeb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:web';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'BackupWeb webRoot data';

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
        $rep = new ToolRepository();
        $result = $rep->backupWebRoot();
        $log = sprintf('[%s], %s, result: %s', REQUEST_ID, __METHOD__, var_export($result, true));
        $this->info($log);
        do_log($log);
    }
}
