<?php

namespace App\Console\Commands;

use App\Http\Resources\TagResource;
use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\HitAndRun;
use App\Models\Medal;
use App\Models\Peer;
use App\Models\SearchBox;
use App\Models\Snatch;
use App\Models\Tag;
use App\Models\User;
use App\Repositories\AgentAllowRepository;
use App\Repositories\AttendanceRepository;
use App\Repositories\ExamRepository;
use App\Repositories\HitAndRunRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TagRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use GeoIp2\Database\Reader;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
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
        $peerId = '-UT355W-%af%b0ky%86N%a6%17i%f8%c1%0a';
        $peerId = '-UT355W-%af%b0ky%86N%a6%17i%f8%c1%0a';
        $peerId = '-UT355W-%AF%B0ky%86N%A6%17i%F8%C1';
        $peerId = '-UT355W-%AF%B0ky%86N%A6%17i%F8%C1%0A';
        $r = strlen(urldecode($peerId));
        dd($r);
    }



}
