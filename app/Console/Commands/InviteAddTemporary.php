<?php

namespace App\Console\Commands;

use App\Jobs\GenerateTemporaryInvite;
use Illuminate\Console\Command;

class InviteAddTemporary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invite:tmp {idRedisKey} {days} {count}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add temporary invite to user, argument: idRedisKey, days, count';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $idRedisKey = $this->argument('idRedisKey');
        $days = $this->argument('days');
        $count = $this->argument('count');
        $log = "idRedisKey: $idRedisKey, days: $days, count: $count";
        $this->info($log);
        do_log($log);
        GenerateTemporaryInvite::dispatch($idRedisKey, $days, $count);
        return Command::SUCCESS;
    }
}
