<?php

namespace App\Console\Commands;

use App\Jobs\GenerateTemporaryInvite;
use App\Jobs\SendLoginNotify;
use Illuminate\Console\Command;

class UserLoginNotify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:login_notify {--this_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send login notify, option: --this_id';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $thisId = $this->option('this_id');
        $this->info("thisId: $thisId");
        if (!$thisId) {
            $this->error("require option --this_id=?");
            return Command::FAILURE;
        }
        SendLoginNotify::dispatch($thisId);
        return Command::SUCCESS;
    }
}
