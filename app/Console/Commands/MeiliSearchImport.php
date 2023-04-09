<?php

namespace App\Console\Commands;

use App\Repositories\MeiliSearchRepository;
use Illuminate\Console\Command;

class MeiliSearchImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meilisearch:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import torrents to meilisearch';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $rep = new MeiliSearchRepository();
        $this->info("going to import torrents...");
        $total = $rep->import();
        $this->info("import $total torrents.");
        return Command::SUCCESS;
    }
}
