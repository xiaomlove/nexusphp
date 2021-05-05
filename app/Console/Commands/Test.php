<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\User;
use App\Repositories\ExamRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
        $rep = new ExamRepository();
//        $r = $rep->assignToUser(1, 1);
//        $r = $rep->addProgress(1, 1, [
//            1 => 25*1024*1024*1024,
//            2 => 55*3600,
//            3 => 10*1024*1024*1024,
//            4 => 1252
//        ]);
//        dd($r);
//        $rep->assignCronjob();
//        $rep->cronjobCheckout();
        $r = DB::select(DB::raw('select version() as info'))[0]->info;
        dd($r);
        Carbon::now()->toDateTimeString();
    }

}
