<?php

namespace App\Console\Commands;

use App\Models\Torrent;
use App\Repositories\RequireSeedTorrentRepository;
use Illuminate\Console\Command;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Just for test';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $rep = new RequireSeedTorrentRepository;
        // $rep->doRemove(Torrent::query()->whereIn('id', [58])->get());
        $rep->autoAddToListCronjob();

        return self::SUCCESS;
    }
}
