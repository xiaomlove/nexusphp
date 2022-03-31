<?php

namespace App\Console\Commands;

use App\Repositories\ToolRepository;
use Illuminate\Console\Command;

class BackuAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup all data, include web root and database.';

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
        $result = $rep->backupAll();
        $log = sprintf(
            '[%s], %s, result: %s',
            nexus()->getRequestId(), __METHOD__, var_export($result, true)
        );
        $this->info($log);
        do_log($log);
    }
}
