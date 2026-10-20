<?php

namespace App\Console\Commands;

use App\Repositories\RequireSeedTorrentRepository;
use Illuminate\Console\Command;

class SeedGuardSync extends Command
{
    protected $signature = 'torrent:seed_guard';

    protected $description = 'Synchronize official torrents in the seed guard section';

    public function handle(RequireSeedTorrentRepository $repository): int
    {
        $result = $repository->syncSeedGuard();
        $this->info(sprintf(
            'Seed guard synchronized: added=%d removed=%d active=%d',
            $result['added'],
            $result['removed'],
            $result['active']
        ));
        return self::SUCCESS;
    }
}
