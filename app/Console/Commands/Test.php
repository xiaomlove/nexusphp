<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\User;
use App\Repositories\ExamRepository;
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
        $user = User::query()->findOrFail(1);
        dd(nexus_trans('exam.checkout_pass_message_content', ['exam_name' => '年中考核', 'begin' => 1, 'end' => 2]));
        $rep = new ExamRepository();
//        $r = $rep->assignToUser(1, 1);
        $r = $rep->addProgress(3, 1, [
            1 => 25*1024*1024*1024,
            2 => 55*3600,
            3 => 100*1024*1024*1024,
            4 => 1252
        ]);
        dd($r);
//        $rep->assignCronjob();
//        $rep->cronjobCheckout();
    }

}
