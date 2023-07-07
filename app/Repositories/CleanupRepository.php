<?php
namespace App\Repositories;

use Carbon\Carbon;
use Nexus\Database\NexusDB;

class CleanupRepository extends BaseRepository
{
    const USER_SEED_BONUS_BATCH_LIST_KEY = "batch_key:user_seed_bonus";
    const USER_SEEDING_LEECHING_TIME_BATCH_LIST_KEY = "batch_key:user_seeding_leeching_time";
    const TORRENT_SEEDERS_ETC_BATCH_LIST_KEY = "batch_key:torrent_seeders_etc";

    public static function recordBatch(\Redis $redis, $uid, $torrentId)
    {
        self::doRecordBatch($redis, self::USER_SEED_BONUS_BATCH_LIST_KEY, $uid);
        self::doRecordBatch($redis, self::USER_SEEDING_LEECHING_TIME_BATCH_LIST_KEY, $uid);
        self::doRecordBatch($redis, self::TORRENT_SEEDERS_ETC_BATCH_LIST_KEY, $torrentId);
    }

    private static function doRecordBatch(\Redis $redis, $batchListKey, $hashKey)
    {
        $batchKey = $redis->rPop($batchListKey);
        if ($batchKey === false) {
            //not exists
            $batchKey = date('YmdHis');
            $redis->lPush($batchListKey, $batchKey);
        }
        $redis->hSetNx($batchKey, $hashKey, date('YmdHis'));
    }


    public static function updateUserLeechingSeedingTime($requestId)
    {
        global $Cache;
        $redis = $Cache->getRedis();

        $logPrefix = sprintf("[CLEANUP_CLI_UPDATE_USER_SEEDING_LEECHING_TIME], commonRequestId: %s", $requestId);
        $beginTimestamp = time();

        $count = 0;
        $size = 1000;
        $batchListKey = self::USER_SEEDING_LEECHING_TIME_BATCH_LIST_KEY;
        $batch = $redis->lPop($batchListKey);
        if ($batch === false) {
            do_log("$logPrefix, batchListKey: $batchListKey, no batch...");
            return;
        }
        $it = NULL;
        /* Don't ever return an empty array until we're done iterating */
        $redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        while($arr_keys = $redis->hScan($batch, $it, "*", $size)) {
            foreach($arr_keys as $uid => $timestamp) {
                do_log("$logPrefix $uid => $timestamp"); /* Print the hash member and value */
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
                    do_log("$logPrefix, [SUCCESS]: $uid => " . json_encode($update));
                    $count++;
                }
            }
            sleep(rand(10, 60));
        }
        $costTime = time() - $beginTimestamp;
        do_log("$logPrefix, [DONE] success update count: $count, cost time: $costTime seconds");
    }

    public static function updateTorrentSeedersEtc($requestId)
    {
        global $Cache;
        $redis = $Cache->getRedis();

        $logPrefix = sprintf("[CLEANUP_CLI_UPDATE_TORRENT_SEEDERS_ETC], commonRequestId: %s", $requestId);
        $beginTimestamp = time();

        $count = 0;
        $size = 1000;
        $batchListKey = self::TORRENT_SEEDERS_ETC_BATCH_LIST_KEY;
        $batch = $redis->lPop($batchListKey);
        if ($batch === false) {
            do_log("$logPrefix, batchListKey: $batchListKey, no batch...");
            return;
        }
        $it = NULL;
        /* Don't ever return an empty array until we're done iterating */
        $redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        while($arr_keys = $redis->hScan($batch, $it, "*", $size)) {
            foreach($arr_keys as $torrentId => $timestamp) {
                do_log("$logPrefix $torrentId => $timestamp"); /* Print the hash member and value */
                $peerResult = NexusDB::table('peers')
                    ->where('torrent', $torrentId)
                    ->selectRaw("count(*) as count, seeder")
                    ->groupBy('seeder')
                    ->get()
                ;
                $commentResult = NexusDB::table('comments')
                    ->where('torrent', $torrentId)
                    ->selectRaw("count(*) as count")
                    ->first()
                ;
                $update = [
                    'comments' => $commentResult && $commentResult->count !== null ? $commentResult->count : 0,
                    'seeders' => 0,
                    'leechers' => 0,
                ];
                foreach ($peerResult as $item) {
                    if ($item->seeder == 'yes') {
                        $update['seeders'] = $item->count;
                    } elseif ($item->seeder == 'no') {
                        $update['leechers'] = $item->count;
                    }
                }
                NexusDB::table('torrents')->where('id', $torrentId)->update($update);
                do_log("$logPrefix, [SUCCESS]: $torrentId => " . json_encode($update));
                $count++;
            }
            sleep(rand(10, 60));
        }
        $costTime = time() - $beginTimestamp;
        do_log("$logPrefix, [DONE] success update count: $count, cost time: $costTime seconds");
    }
}
