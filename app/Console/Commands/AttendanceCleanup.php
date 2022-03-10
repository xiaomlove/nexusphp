<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;

class AttendanceCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup attendance data.';

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
        $query = Attendance::query()->groupBy('uid')->selectRaw('uid, max(id) as max_id');
        $page = 1;
        $size = 10000;
        while (true) {
            $rows = $query->forPage($page, $size)->get();
            $log = "sql: " . last_query() . ", count: " . $rows->count();
            do_log($log);
            $this->info($log);
            if ($rows->isEmpty()) {
                $log = "no more data....";
                do_log($log);
                $this->info($log);
                break;
            }
            foreach ($rows as $row) {
                do {
                    $deleted = Attendance::query()
                        ->where('uid', $row->uid)
                        ->where('id', '<', $row->max_id)
                        ->limit(10000)
                        ->delete();
                    $log = "delete: $deleted by sql: " . last_query();
                    do_log($log);
                    $this->info($log);
                } while ($deleted > 0);
            }
            $page++;
        }
        return 0;
    }
}
