<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\SearchBox;
use App\Models\User;
use App\Repositories\ExamRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TorrentRepository;
use Carbon\Carbon;
use GeoIp2\Database\Reader;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Rhilip\Bencode\Bencode;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Just for test';

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
        $page = 1;
        $size = 1000;
        while (true) {
            $logPrefix = "[MIGRATE_ATTENDANCE], page: $page, size: $size";
            $result = Attendance::query()
                ->groupBy(['uid'])
                ->selectRaw('uid, max(id) as id, count(*) as counts')
                ->forPage($page, $size)
                ->get();
            do_log("$logPrefix, " . last_query() . ", count: " . $result->count());
            if ($result->isEmpty()) {
                do_log("$logPrefix, no more data...");
                break;
            }
            foreach ($result as $row) {
                $update = [
                    'total_days' => $row->counts,
                ];
                $updateResult = $row->update($update);
                do_log(sprintf(
                    "$logPrefix, update user: %s(ID: %s) => %s, result: %s",
                    $row->uid, $row->id, json_encode($update), var_export($updateResult, true)
                ));
            }
            $page++;
        }
    }

}
