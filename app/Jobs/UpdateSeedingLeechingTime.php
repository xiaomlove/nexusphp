<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nexus\Database\NexusDB;

class UpdateSeedingLeechingTime implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $beginUid;

    private int $endUid;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $beginUid, int $endUid)
    {
        $this->beginUid = $beginUid;
        $this->endUid = $endUid;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $beginTimestamp = time();
        $logPrefix = sprintf("[CLEANUP_CLI_UPDATE_SEEDING_LEECHING_TIME], beginUid: %s, endUid: %s", $this->beginUid, $this->endUid);
        $sql = sprintf("select id from users where id > %s and id <= %s and enabled = 'yes' and status = 'confirmed'", $this->beginUid, $this->endUid);
        $results = NexusDB::select($sql);
        do_log("$logPrefix, [GET_UID], sql: $sql, count: " . count($results));
        foreach ($results as $arr) {
            $uid = $arr['id'];
            $sql = sprintf('select sum(seedtime) as st, sum(leechtime) as lt from snatched where userid = %s limit 1', $uid);
            $row = NexusDB::select($sql);
            if (is_numeric($row[0]['st'])) {
                $sql = sprintf('update users set seedtime = %s, leechtime = %s where id = %s limit 1', $row[0]['st'], $row[0]['lt'], $uid);
                NexusDB::statement($sql);
            }
        }
        $costTime = time() - $beginTimestamp;
        do_log("$logPrefix, [DONE], cost time: $costTime seconds");
    }
}
