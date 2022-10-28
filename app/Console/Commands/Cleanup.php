<?php

namespace App\Console\Commands;

use App\Jobs\CalculateSeedBonus;
use App\Jobs\UpdateSeedingLeechingTime;
use Illuminate\Console\Command;

class Cleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup {--action=} {--begin_uid=} {--end_uid=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup async job trigger, options: --begin_uid, --end_uid, --action (seed_bonus, seeding_leeching_time)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $action = $this->option('action');
        $beginUid = $this->option('begin_uid');
        $endUid = $this->option('end_uid');
        $this->info("beginUid: $beginUid, endUid: $endUid, action: $action");
        if ($action == 'seed_bonus') {
            CalculateSeedBonus::dispatch($beginUid, $endUid);
        } elseif ($action == 'seeding_leeching_time') {
            UpdateSeedingLeechingTime::dispatch($beginUid, $endUid);
        } else {
            $msg = "Invalid action: $action";
            do_log($msg, 'error');
            $this->error($msg);
        }
        return Command::SUCCESS;
    }
}
