<?php

namespace App\Console\Commands;

use App\Services\OfficialSeedActivityService;
use Illuminate\Console\Command;

class OfficialSeedActivitySync extends Command
{
    protected $signature = 'torrent:official_seed_activity';

    protected $description = 'Synchronize and settle the NovaHD official torrent seeding activity';

    public function handle(OfficialSeedActivityService $service): int
    {
        $result = $service->sync();
        $this->info(json_encode($result, JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
