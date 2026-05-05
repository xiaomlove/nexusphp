<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Nexus\Database\NexusDB;

class UpdateUserSeedingLeechingTime implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $beginUid;

    private int $endUid;

    private string $requestId;

    private ?string $idStr = null;

    private string $idRedisKey;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $beginUid, int $endUid, string $idStr, string $idRedisKey, string $requestId = '')
    {
        $this->beginUid = $beginUid;
        $this->endUid = $endUid;
        $this->idStr = $idStr;
        $this->idRedisKey = $idRedisKey;
        $this->requestId = $requestId;
    }

    public $tries = 1;

    public $timeout = 3600;

    /**
     * 获取任务时，应该通过的中间件。
     *
     * @return array
     */
    public function middleware()
    {
        return [new WithoutOverlapping($this->idRedisKey)];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $beginTimestamp = time();
        $logPrefix = sprintf(
            '[CLEANUP_CLI_UPDATE_SEEDING_LEECHING_TIME_HANDLE_JOB], commonRequestId: %s, beginUid: %s, endUid: %s, idStr: %s, idRedisKey: %s',
            $this->requestId, $this->beginUid, $this->endUid, $this->idStr, $this->idRedisKey,
        );
        do_log("$logPrefix, job start ...");

        $idStr = $this->idStr;
        $delIdRedisKey = false;
        if (empty($idStr) && ! empty($this->idRedisKey)) {
            $delIdRedisKey = true;
            $idStr = NexusDB::cache_get($this->idRedisKey);
        }
        if (empty($idStr)) {
            do_log("$logPrefix, no idStr or idRedisKey", 'error');

            return;
        }
        // 批量取，简单化
        $res = NexusDB::table('snatched')
            ->selectRaw('userid, sum(seedtime) as seedtime_sum, sum(leechtime) as leechtime_sum')
            ->whereRaw("userid in ($idStr)")
            ->groupBy('userid')
            ->get();
        if ($res->isEmpty()) {
            do_log("$logPrefix, no data from idStr: $idStr", 'error');

            return;
        }
        $seedtimeUpdates = $leechTimeUpdates = [];
        $nowStr = now()->toDateTimeString();
        $count = 0;
        foreach ($res as $row) {
            $count++;
            $seedtimeUpdates[] = sprintf('when %d then %d', $row->userid, $row->seedtime_sum ?? 0);
            $leechTimeUpdates[] = sprintf('when %d then %d', $row->userid, $row->leechtime_sum ?? 0);
        }
        $sql = sprintf(
            "update users set seedtime = case id %s end, leechtime = case id %s end, seed_time_updated_at = '%s' where id in (%s)",
            implode(' ', $seedtimeUpdates), implode(' ', $leechTimeUpdates), $nowStr, $idStr
        );
        $result = NexusDB::statement($sql);
        if ($delIdRedisKey) {
            NexusDB::cache_del($this->idRedisKey);
        }
        $costTime = time() - $beginTimestamp;
        do_log(sprintf(
            "$logPrefix, [DONE], update user count: %s, result: %s, cost time: %s seconds",
            $count, var_export($result, true), $costTime
        ));
        do_log("$logPrefix, sql: $sql", 'debug');
    }

    /**
     * Handle a job failure.
     *
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        do_log('failed: '.$exception->getMessage().$exception->getTraceAsString(), 'error');
    }
}
