<?php

namespace App\Console\Commands;

use App\Repositories\ToolRepository;
use Illuminate\Console\Command;

class BackupCronjob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:cronjob {--force=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup all data cronjob, and upload to Google drive. options: --force';

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
    public function handle(): int
    {
        $force = $this->option('force');
        $this->info("force: $force");
        $rep = new ToolRepository();
        $result = $rep->cronjobBackup($force);
        $log = sprintf(
            '[%s], %s, result: %s',
            nexus()->getRequestId(), __METHOD__, var_export($result, true)
        );
        $this->info($log);
        do_log($log);
        return 0;
    }
}
