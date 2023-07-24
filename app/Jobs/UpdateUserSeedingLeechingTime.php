<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Repositories\CleanupRepository;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nexus\Database\NexusDB;

class UpdateUserSeedingLeechingTime implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $beginUid;

    private int $endUid;

    private string $requestId;

    private string $idStr;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $beginUid, int $endUid, string $idStr, string $requestId = '')
    {
        $this->beginUid = $beginUid;
        $this->endUid = $endUid;
        $this->idStr = $idStr;
        $this->requestId = $requestId;
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        return now()->addSeconds(Setting::get('main.autoclean_interval_four'));
    }

    public $tries = 1;

    public $timeout = 3600;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $beginTimestamp = time();
        $logPrefix = sprintf("[CLEANUP_CLI_UPDATE_SEEDING_LEECHING_TIME_HANDLE_JOB], commonRequestId: %s, beginUid: %s, endUid: %s", $this->requestId, $this->beginUid, $this->endUid);

        $count = 0;
        $uidArr = explode(",", $this->idStr);
        foreach ($uidArr as $uid) {
            if ($uid <= 0) {
                continue;
            }
            $sumInfo = NexusDB::table('snatched')
                ->selectRaw('sum(seedtime) as seedtime_sum, sum(leechtime) as leechtime_sum')
                ->where('userid', $uid)
                ->first();
            if ($sumInfo && $sumInfo->seedtime_sum !== null) {
                $update = [
                    'seedtime' => $sumInfo->seedtime_sum ?? 0,
                    'leechtime' => $sumInfo->leechtime_sum ?? 0,
                    'seed_time_updated_at' => Carbon::now()->toDateTimeString(),
                ];
                NexusDB::table('users')
                    ->where('id', $uid)
                    ->update($update);
                do_log("[CLEANUP_CLI_UPDATE_SEEDING_LEECHING_TIME_HANDLE_USER], [SUCCESS]: $uid => " . json_encode($update));
                $count++;
            }
        }
        $costTime = time() - $beginTimestamp;
        do_log("$logPrefix, [DONE], user total count: " . count($uidArr) . ", success update count: $count, cost time: $costTime seconds");
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        do_log("failed: " . $exception->getMessage() . $exception->getTraceAsString(), 'error');
    }
}
