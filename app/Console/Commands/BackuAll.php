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
    protected $signature = 'backup:all {--upload-to-google-drive}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup all data, include web root and database. options: --upload-to-google-drive';

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
        $uploadToGoogleDrive = $this->option('upload-to-google-drive');
        $rep = new ToolRepository();
        $result = $rep->backupAll($uploadToGoogleDrive);
        $log = sprintf(
            '[%s], %s, uploadToGoogleDrive: %s, result: %s',
            REQUEST_ID, __METHOD__, var_export($uploadToGoogleDrive, true), var_export($result, true)
        );
        $this->info($log);
        do_log($log);
    }
}
