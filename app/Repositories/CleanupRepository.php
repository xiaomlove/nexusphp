<?php
namespace App\Repositories;

use Carbon\Carbon;
use Nexus\Database\NexusDB;

class CleanupRepository extends BaseRepository
{
    const USER_SEED_BONUS_BATCH_KEY = "batch_key:user_seed_bonus";
    const USER_SEEDING_LEECHING_TIME_BATCH_KEY = "batch_key:user_seeding_leeching_time";
    const TORRENT_SEEDERS_ETC_BATCH_KEY = "batch_key:torrent_seeders_etc";

    public static function recordBatch(\Redis $redis, $uid, $torrentId)
    {
        $args = [
            self::USER_SEEDING_LEECHING_TIME_BATCH_KEY, self::TORRENT_SEEDERS_ETC_BATCH_KEY,
            $uid, $torrentId, self::getHashKeySuffix()
        ];
        $result  = $redis->eval(self::getAddRecordLuaScript(), $args, 2);
        $err = $redis->getLastError();
        if ($err) {
            do_log("[REDIS_LUA_ERROR]: $err", "error");
        }
        return $result;
    }

    public static function runBatchJob($batchKey, $requestId)
    {
        $redis = NexusDB::redis();
        $logPrefix = sprintf("[$batchKey], commonRequestId: %s", $requestId);
        $beginTimestamp = time();

        $batch = self::getBatch($redis, $batchKey);
        if (!$batch) {
            do_log("$logPrefix, batchKey: $batchKey no batch...", 'error');
            return;
        }
        //update the batch key
        $redis->set($batchKey, $batchKey . ":" . self::getHashKeySuffix());
        $count = match ($batchKey) {
            self::USER_SEEDING_LEECHING_TIME_BATCH_KEY => self::updateUserLeechingSeedingTime($redis, $batch, $logPrefix),
            self::TORRENT_SEEDERS_ETC_BATCH_KEY => self::updateTorrentSeedersEtc($redis, $batch, $logPrefix),
            default => throw new \InvalidArgumentException("Invalid batchKey: $batchKey")
        };
        //remove this batch
        $redis->del($batch);
        $endTimestamp = time();
        do_log(sprintf("$logPrefix, [DONE], batch: $batch, count: $count, cost time: %d seconds", $endTimestamp - $beginTimestamp));
    }


    private static function updateUserLeechingSeedingTime(\Redis $redis, $batch, $logPrefix): int
    {
        $count = 0;
        $size = 1000;
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
            sleep(rand(1, 10));
        }
        return $count;
    }

    private static function updateTorrentSeedersEtc(\Redis $redis, $batch, $logPrefix)
    {
        $count = 0;
        $size = 1000;
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
            sleep(rand(1, 10));
        }
        return $count;
    }

    private static function getBatch(\Redis $redis, $batchKey)
    {
        $batch = $redis->get($batchKey);
        if ($batch === false) {
            do_log("batchKey: $batchKey, no batch...", 'error');
            return false;
        }
        if (!$redis->exists($batch)) {
            do_log("batch: $batch, not exists...", 'error');
            return false;
        }
        return $batch;
    }

    /**
     * USER_SEEDING_LEECHING_TIME, TORRENT_SEEDERS_ETC, uid, torrentId, timeStr
     *
     * @return string
     */
    private static function getAddRecordLuaScript(): string
    {
        return <<<'LUA'
local batchList = {KEYS[1], KEYS[2]}
for k, v in pairs(batchList) do
    local batchKey = redis.call("GET", v)
    local isBatchKeyNew = false
    if batchKey == false then
        batchKey = v .. ":" .. ARGV[3]
        redis.call("SET", v, batchKey, "EX", 2592000)
        isBatchKeyNew = true
    end
    local hashKey
    if k == 1 then
        hashKey = ARGV[1]
    else
        hashKey = ARGV[2]
    end
    redis.call("HSETNX", batchKey, hashKey, ARGV[3])
    if isBatchKeyNew then
        redis.call("EXPIRE", batchKey, 2592000)
    end
end
LUA;
    }

    private static function getHashKeySuffix(): string
    {
        return date('Ymd_His');
    }

}
