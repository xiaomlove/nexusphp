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
        $r = urldecode('bm9wZQ%3D%3D');
        $r = base64_decode($r);
        dd($r);
    }

}
