<?php

namespace App\Jobs;

use App\Models\Setting;
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

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $beginUid, int $endUid, string $requestId = '')
    {
        $this->beginUid = $beginUid;
        $this->endUid = $endUid;
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

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $beginTimestamp = time();
        $logPrefix = sprintf("[CLEANUP_CLI_UPDATE_SEEDING_LEECHING_TIME], commonRequestId: %s, beginUid: %s, endUid: %s", $this->requestId, $this->beginUid, $this->endUid);
//        $sql = sprintf(
//            "update users set seedtime = (select sum(seedtime) from snatched where userid = users.id), leechtime=(select sum(leechtime) from snatched where userid = users.id), seed_time_updated_at = '%s' where id > %s and id <= %s and status = 'confirmed' and enabled = 'yes'",
//            now()->toDateTimeString(), $this->beginUid, $this->endUid
//        );
//        $results = NexusDB::statement($sql);

        $users = NexusDB::table('users')
            ->where('id', '>', $this->beginUid)
            ->where('id', '<=', $this->endUid)
            ->where('status', 'confirmed')
            ->where('enabled', 'yes')
            ->get(['id'])
        ;
        $count = 0;
        foreach ($users as $user) {
            $sumInfo = NexusDB::table('snatched')
                ->selectRaw('sum(seedtime) as seedtime_sum, sum(leechtime) as leechtime_sum')
                ->where('userid', $user->id)
                ->first();
            if ($sumInfo && $sumInfo->seedtime_sum !== null) {
                $update = [
                    'seedtime' => $sumInfo->seedtime_sum ?? 0,
                    'leechtime' => $sumInfo->leechtime_sum ?? 0,
                    'seed_time_updated_at' => Carbon::now()->toDateTimeString(),
                ];
                NexusDB::table('users')
                    ->where('id', $user->id)
                    ->update($update);
                do_log("$logPrefix, [SUCCESS]: $user->id => " . json_encode($update));
                $count++;
            }
        }
        $costTime = time() - $beginTimestamp;
        do_log("$logPrefix, [DONE], user total count: " . count($users) . ", success update count: $count, cost time: $costTime seconds");
    }
}
