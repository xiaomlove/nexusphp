<?php
namespace App\Repositories;

use App\Models\Setting;
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
            self::USER_SEED_BONUS_BATCH_KEY, self::USER_SEEDING_LEECHING_TIME_BATCH_KEY, self::TORRENT_SEEDERS_ETC_BATCH_KEY,
            $uid, $uid, $torrentId, self::getHashKeySuffix()
        ];
        $result  = $redis->eval(self::getAddRecordLuaScript(), $args, 3);
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
            self::USER_SEED_BONUS_BATCH_KEY => self::calculateUserSeedBonus($redis, $batch, $logPrefix),
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
     * USER_SEED_BONUS, USER_SEEDING_LEECHING_TIME, TORRENT_SEEDERS_ETC, uid, uid, torrentId, timeStr
     *
     * @return string
     */
    private static function getAddRecordLuaScript(): string
    {
        return <<<'LUA'
local batchList = {KEYS[1], KEYS[2], KEYS[3]}
for k, v in pairs(batchList) do
    local batchKey = redis.call("GET", v)
    local isBatchKeyNew = false
    if batchKey == false then
        batchKey = v .. ":" .. ARGV[4]
        redis.call("SET", v, batchKey, "EX", 2592000)
        isBatchKeyNew = true
    end
    local hashKey
    if (k == 1)
    then
        hashKey = ARGV[1]
    elseif (k == 2)
    then
        hashKey = ARGV[2]
    else
        hashKey = ARGV[3]
    end
    redis.call("HSETNX", batchKey, hashKey, ARGV[4])
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

    private static function calculateUserSeedBonus(\Redis $redis, $batch, $logPrefix)
    {
        $haremAdditionFactor = Setting::get('bonus.harem_addition');
        $officialAdditionFactor = Setting::get('bonus.official_addition');
        $donortimes_bonus = Setting::get('bonus.donortimes');
        $autoclean_interval_one = Setting::get('main.autoclean_interval_one');

        $logFile = getLogFile("seed-bonus-points");
        do_log("$logPrefix, logFile: $logFile");
        $fd = fopen($logFile, 'a');

        $count = 0;
        $size = 1000;
        $it = NULL;
        /* Don't ever return an empty array until we're done iterating */
        $redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        while($arr_keys = $redis->hScan($batch, $it, "*", $size)) {
            foreach($arr_keys as $uid => $timestamp) {
                do_log("$logPrefix $uid => $timestamp"); /* Print the hash member and value */

                $userInfo = get_user_row($uid);
                $isDonor = is_donor($userInfo);
                $seedBonusResult = calculate_seed_bonus($uid);
                $bonusLog = "[CLEANUP_CLI_CALCULATE_SEED_BONUS], user: $uid, seedBonusResult: " . nexus_json_encode($seedBonusResult);
                $all_bonus = $seedBonusResult['seed_bonus'];
                $bonusLog .= ", all_bonus: $all_bonus";
                if ($isDonor) {
                    $all_bonus = $all_bonus * $donortimes_bonus;
                    $bonusLog .= ", isDonor, donortimes_bonus: $donortimes_bonus, all_bonus: $all_bonus";
                }
                if ($officialAdditionFactor > 0) {
                    $officialAddition = $seedBonusResult['official_bonus'] * $officialAdditionFactor;
                    $all_bonus += $officialAddition;
                    $bonusLog .= ", officialAdditionFactor: $officialAdditionFactor, official_bonus: {$seedBonusResult['official_bonus']}, officialAddition: $officialAddition, all_bonus: $all_bonus";
                }
                if ($haremAdditionFactor > 0) {
                    $haremBonus = calculate_harem_addition($uid);
                    $haremAddition =  $haremBonus * $haremAdditionFactor;
                    $all_bonus += $haremAddition;
                    $bonusLog .= ", haremAdditionFactor: $haremAdditionFactor, haremBonus: $haremBonus, haremAddition: $haremAddition, all_bonus: $all_bonus";
                }
                if ($seedBonusResult['medal_additional_factor'] > 0) {
                    $medalAddition = $seedBonusResult['medal_bonus'] * $seedBonusResult['medal_additional_factor'];
                    $all_bonus += $medalAddition;
                    $bonusLog .= ", medalAdditionFactor: {$seedBonusResult['medal_additional_factor']}, medalBonus: {$seedBonusResult['medal_bonus']}, medalAddition: $medalAddition, all_bonus: $all_bonus";
                }
                $dividend = 3600 / $autoclean_interval_one;
                $all_bonus = $all_bonus / $dividend;
                $seed_points = $seedBonusResult['seed_points'] / $dividend;
                $updatedAt = now()->toDateTimeString();
                $sql = "update users set seed_points = ifnull(seed_points, 0) + $seed_points, seedbonus = seedbonus + $all_bonus, seed_points_per_hour = {$seedBonusResult['seed_points']} ,seed_points_updated_at = '$updatedAt' where id = $uid limit 1";
                do_log("$bonusLog, query: $sql");
                NexusDB::statement($sql);
                if ($fd) {
                    $log = sprintf(
                        '%s|%s|%s|%s|%s|%s|%s|%s',
                        date('Y-m-d H:i:s'), $uid,
                        $userInfo['seed_points'], number_format($seed_points, 1, '.', ''),  number_format($userInfo['seed_points'] + $seed_points, 1, '.', ''),
                        $userInfo['seedbonus'], number_format($all_bonus, 1, '.', ''),  number_format($userInfo['seedbonus'] + $all_bonus, 1, '.', '')
                    );
                    fwrite($fd, $log . PHP_EOL);
                } else {
                    do_log("logFile: $logFile is not writeable!", 'error');
                }
                $count++;
            }
            sleep(rand(1, 10));
        }
        return $count;
    }

}
