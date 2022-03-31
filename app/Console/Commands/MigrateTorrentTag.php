<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Repositories\TagRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class MigrateTorrentTag extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'torrent:migrate_tags';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate exits torrent tags to new structure';

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
        $rep = new TagRepository();
        $result = $rep->migrateTorrentTag();
        $log = sprintf('[%s], %s, result: %s, query: %s', nexus()->getRequestId(), __METHOD__, var_export($result, true), last_query());
        $this->info($log);
        do_log($log);
        return 0;
    }
}
