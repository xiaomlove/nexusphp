<?php

namespace App\Console\Commands;

use App\Jobs\LoadTorrentBoughtUsers;
use App\Repositories\TorrentRepository;
use Illuminate\Console\Command;

class TorrentLoadBoughtUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'torrent:load_bought_user {torrent_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Load torrent all bought users. argument: torrent_id';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $torrentId = $this->argument('torrent_id');
        LoadTorrentBoughtUsers::dispatch($torrentId);
        do_log("torrentId: $torrentId");
        return Command::SUCCESS;
    }
}
