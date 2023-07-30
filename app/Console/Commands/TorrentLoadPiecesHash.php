<?php

namespace App\Console\Commands;

use App\Repositories\TorrentRepository;
use Illuminate\Console\Command;

class TorrentLoadPiecesHash extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'torrent:load_pieces_hash {--id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Load torrent pieces hash to cache';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $begin = time();
        $id = $this->option('id');
        $rep = new TorrentRepository();
        $this->info("id: $id, going to load pieces hash...");
        $total = $rep->loadPiecesHashCache($id);
        $this->info(sprintf("%s, total: %s, cost time: %s seconds.", nexus()->getRequestId(), $total, time() - $begin));
        return Command::SUCCESS;
    }
}
