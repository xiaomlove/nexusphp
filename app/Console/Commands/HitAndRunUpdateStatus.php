<?php

namespace App\Console\Commands;

use App\Repositories\HitAndRunRepository;
use Illuminate\Console\Command;

class HitAndRunUpdateStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:update_status {--uid=} {--torrent_id=}  {--ignore_time=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update H&R status, options: --uid, --torrent_id, --ignore_time';

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
        $uid = $this->option('uid');
        $torrentId = $this->option('torrent_id');
        $ignoreTime = $this->option('ignore_time');
        $rep = new HitAndRunRepository();
        $result = $rep->cronjobUpdateStatus($uid, $torrentId, $ignoreTime);
        $log = sprintf(
            '[%s], %s, uid: %s, torrentId: %s, result: %s',
            nexus()->getRequestId(), __METHOD__, $uid, $torrentId, var_export($result, true)
        );
        $this->info($log);
        do_log($log);
        return 0;
    }
}
