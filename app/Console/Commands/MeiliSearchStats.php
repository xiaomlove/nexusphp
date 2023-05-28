<?php

namespace App\Console\Commands;

use App\Repositories\MeiliSearchRepository;
use Illuminate\Console\Command;

class MeiliSearchStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meilisearch:stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get meilisearch stats info';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $rep = new MeiliSearchRepository();
        dump($rep->getClient()->stats());
        return Command::SUCCESS;
    }
}
