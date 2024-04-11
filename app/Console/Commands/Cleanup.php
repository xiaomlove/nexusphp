<?php

namespace App\Console\Commands;

use App\Jobs\CalculateUserSeedBonus;
use App\Jobs\UpdateTorrentSeedersEtc;
use App\Jobs\UpdateUserSeedingLeechingTime;
use Illuminate\Console\Command;

class Cleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup {--action=} {--begin_id=} {--id_str=} {--end_id=} {--request_id=} {--delay=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup async job trigger, options: --begin_id, --end_id, --id_str, --request_id, --delay, --action (seed_bonus, seeding_leeching_time, seeders_etc)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $action = $this->option('action');
        $beginId = $this->option('begin_id');
        $endId = $this->option('end_id');
        $idStr = $this->option('id_str') ?: "";
        $commentRequestId = $this->option('request_id');
        $delay = $this->option('delay') ?: 0;
        $this->info("beginId: $beginId, endId: $endId, idStr: $idStr, commentRequestId: $commentRequestId, delay: $delay, action: $action");
        if ($action == 'seed_bonus') {
            CalculateUserSeedBonus::dispatch($beginId, $endId, $idStr, $commentRequestId)->delay($delay);
        } elseif ($action == 'seeding_leeching_time') {
            UpdateUserSeedingLeechingTime::dispatch($beginId, $endId, $idStr, $commentRequestId)->delay($delay);
        }elseif ($action == 'seeders_etc') {
            UpdateTorrentSeedersEtc::dispatch($beginId, $endId, $idStr, $commentRequestId)->delay($delay);
        } else {
            $msg = "[$commentRequestId], Invalid action: $action";
            do_log($msg, 'error');
            $this->error($msg);
        }
        return Command::SUCCESS;
    }
}
