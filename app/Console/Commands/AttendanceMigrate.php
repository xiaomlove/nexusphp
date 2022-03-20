<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Repositories\AttendanceRepository;
use Illuminate\Console\Command;

class AttendanceMigrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate attendance from one time one record to one user one record.';

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
        $rep = new AttendanceRepository();
        $result = $rep->migrateAttendance();
        $log = sprintf('[%s], %s, result: %s, query: %s', nexus() ? nexus()->getRequestId() : 'NO_REQUEST_ID', __METHOD__, var_export($result, true), last_query());
        $this->info($log);
        do_log($log);
        return 0;
    }
}
