<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\SearchBox;
use App\Models\User;
use App\Repositories\ExamRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TorrentRepository;
use Carbon\Carbon;
use Doctrine\DBAL\Schema\Schema;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
//        $r = \Illuminate\Support\Facades\Schema::getColumnListing('torrents');
//        $r = urldecode('%b5%8f%7c%a9%85%ed%e2%bb%09%fd1%ab%8d%11%e5%11%bb%18%deD');
//        $r = bin2hex($r);
        $str = 'passkey=bef88d0cbe4ccbc1569b8404d09c4c5a&info_hash=%cd%8d%5b%09%08%d7%1d%01_o8%c0%e1Wd%ff%95%84J%e1&peer_id=-TR3000-zxcl8rs3my5o&port=51416&uploaded=0&downloaded=0&left=0&numwant=80&key=2d2ebd37&compact=1&supportcrypto=1&ipv6=240e%3A3b1%3A6400%3Ac20%3A211%3A32ff%3Afebb%3A9fb1';
        $firstNeedle = "info_hash=";
        $start = strpos($str, $firstNeedle) + strlen($firstNeedle);
        $end = strpos($str, "&", $start);
        $r = substr($str, $start, $end - $start);
        dd($r);
    }

}
