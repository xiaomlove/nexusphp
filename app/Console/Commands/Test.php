<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\HitAndRun;
use App\Models\Medal;
use App\Models\SearchBox;
use App\Models\Snatch;
use App\Models\User;
use App\Repositories\AgentAllowRepository;
use App\Repositories\ExamRepository;
use App\Repositories\HitAndRunRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\UserRepository;
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
        $peerId = '-TR2920-9bqp8iu7v9se';
        $agent = 'Transmission/2.92';
        $rep = new AgentAllowRepository();
//        $r = $rep->checkClient($peerId, $agent, true);
        $r = array_slice([1,2], 1, 3);
        dd($r);
    }

}
