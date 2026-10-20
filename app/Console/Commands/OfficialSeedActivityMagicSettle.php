<?php

namespace App\Console\Commands;

use App\Services\OfficialSeedActivityMagicService;
use Illuminate\Console\Command;

class OfficialSeedActivityMagicSettle extends Command
{
    protected $signature = 'torrent:official_seed_activity_magic';

    protected $description = 'Settle the separate NovaHD official seed activity magic rewards';

    public function handle(OfficialSeedActivityMagicService $service): int
    {
        $this->info(json_encode($service->settle(), JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
