<?php

namespace App\Console\Commands;

use App\Events\TorrentCreated;
use Illuminate\Console\Command;

class TorrentFetchImdb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'torrent:fetch_imdb {torrent_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch torrent imdb info';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $torrentId = $this->argument("torrent_id");
        $this->info("torrentId: $torrentId");
        if (!$torrentId) {
            $this->error("require argument torrent_id");
            return Command::FAILURE;
        }
        TorrentCreated::dispatch($torrentId);
        return Command::SUCCESS;
    }
}
