<?php

namespace App\Console\Commands;

use App\Repositories\SearchRepository;
use Illuminate\Console\Command;

class EsDeleteIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:delete_index';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete index in Elasticsearch';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $rep = new SearchRepository();
        $result = $rep->deleteIndex();
        $log = sprintf("[%s], %s, result: \n%s", nexus()->getRequestId(), __METHOD__, var_export($result, true));
        $this->info($log);
        do_log($log);
        return 0;
    }
}
