<?php
namespace App\Repositories;

use App\Http\Middleware\Locale;
use App\Models\Avp;
use App\Models\NexusModel;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Nexus\Database\NexusDB;

class CleanupRepository extends BaseRepository
{
    const USER_SEED_BONUS_BATCH_KEY = "batch_key:user_seed_bonus";
    const USER_SEEDING_LEECHING_TIME_BATCH_KEY = "batch_key:user_seeding_leeching_time";
    const TORRENT_SEEDERS_ETC_BATCH_KEY = "batch_key:torrent_seeders_etc";

    const IDS_KEY_PREFIX = "cleanup_batch_job_ids:";

    private static array $batchKeyActionsMap = [
        self::USER_SEED_BONUS_BATCH_KEY => [
            'action' => 'seed_bonus',
            'task_index' => 0,
        ],
        self::TORRENT_SEEDERS_ETC_BATCH_KEY => [
            'action' => 'seeders_etc',
            'task_index' => 1,
        ],
        self::USER_SEEDING_LEECHING_TIME_BATCH_KEY => [
            'action' => 'seeding_leeching_time',
            'task_index' => 2,
        ],
    ];

    private static int $totalTask = 3;

    private static int $oneTaskSeconds = 0;

    private static int $scanSize = 1000;

    public static function recordBatch(\Redis $redis, $uid, $torrentId)
    {
        $args = [
            self::USER_SEED_BONUS_BATCH_KEY, self::USER_SEEDING_LEECHING_TIME_BATCH_KEY, self::TORRENT_SEEDERS_ETC_BATCH_KEY,
            $uid, $uid, $torrentId, self::getHashKeySuffix(), self::getCacheKeyLifeTime(), time(),
        ];
        $result  = $redis->eval(self::getAddRecordLuaScript(), $args, 3);
        $err = $redis->getLastError();
        if ($err) {
            do_log("[REDIS_LUA_ERROR]: $err", "error");
        }
        return $result;
    }

    public static function runBatchJobCalculateUserSeedBonus(string $requestId)
    {
        self::runBatchJob(self::USER_SEED_BONUS_BATCH_KEY, $requestId);
    }

    public static function runBatchJobUpdateUserSeedingLeechingTime(string $requestId)
    {
        self::runBatchJob(self::USER_SEEDING_LEECHING_TIME_BATCH_KEY, $requestId);
    }

    public static function runBatchJobUpdateTorrentSeedersEtc(string $requestId)
    {
        self::runBatchJob(self::TORRENT_SEEDERS_ETC_BATCH_KEY, $requestId);
    }

    private static function runBatchJob($batchKey, $requestId)
    {
        $redis = NexusDB::redis();
        $logPrefix = sprintf("[$batchKey], commonRequestId: %s", $requestId);
        $beginTimestamp = time();
        if (!isset(self::$batchKeyActionsMap[$batchKey])) {
            do_log("$logPrefix, batchKey: $batchKey invalid", 'error');
            return;
        }
        $batchKeyInfo = self::$batchKeyActionsMap[$batchKey];

        $batch = self::getBatch($redis, $batchKey);
        if (!$batch) {
            do_log("$logPrefix, batchKey: $batchKey no batch...", 'error');
            return;
        }
        //update the batch key
        //用户魔力部分不更新，避免用户保旧种汇报时间过长影响魔力增加
        if ($batchKey != self::USER_SEED_BONUS_BATCH_KEY) {
            $newBatch = $batchKey . ":" . self::getHashKeySuffix();
            $lifeTime = self::getCacheKeyLifeTime();
            $redis->set($batchKey, $newBatch, ['ex' => $lifeTime]);
            $redis->hSetNx($newBatch, -1, 1);
            $redis->expire($newBatch, $lifeTime);
        }

        $userSeedBonusDeadline = deadtime();
        $count = 0;
        $it = NULL;
        $length = $redis->hLen($batch);
        $page = 0;
        /* Don't ever return an empty array until we're done iterating */
        $redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        while($arr_keys = $redis->hScan($batch, $it, "*", self::$scanSize)) {
            $delay = self::getDelay($batchKeyInfo['task_index'], $length, $page);
            $toRemoveFields = $validFields = [];
            foreach ($arr_keys as $field => $value) {
                if ($batchKey == self::USER_SEED_BONUS_BATCH_KEY && $value < $userSeedBonusDeadline) {
                    //dead, should remove
                    $toRemoveFields[] = $field;
                } else {
                    $validFields[] = $field;
                }
            }
            if (!empty($validFields)) {
                $idStr = implode(",", $validFields);
                $idRedisKey = self::IDS_KEY_PREFIX . Str::random();
                NexusDB::cache_put($idRedisKey, $idStr);
                $command = sprintf(
                    'cleanup --action=%s --begin_id=%s --end_id=%s --id_redis_key=%s --request_id=%s --delay=%s',
                    $batchKeyInfo['action'], 0, 0,  $idRedisKey, $requestId, $delay
                );
                $output = executeCommand($command, 'string', true);
                do_log(sprintf('output: %s', $output));
                $count += count($validFields);
            }
            if (!empty($toRemoveFields)) {
                $redis->hDel($batch, ...$toRemoveFields);
            }
            $page++;
        }

        //remove this batch
        if ($batchKey != self::USER_SEED_BONUS_BATCH_KEY) {
            $redis->del($batch);
        }
        $endTimestamp = time();
        do_log(sprintf("$logPrefix, [DONE], batch: $batch, count: $count, cost time: %d seconds", $endTimestamp - $beginTimestamp));
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
     * USER_SEED_BONUS, USER_SEEDING_LEECHING_TIME, TORRENT_SEEDERS_ETC,
     * uid, uid, torrentId, timeStr, cacheLifeTime, nowTimestamp
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
        redis.call("SET", v, batchKey)
        if (k > 1) then
            redis.call("EXPIRE", v, ARGV[5])
        end
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
    redis.call("HSET", batchKey, hashKey, ARGV[6])
    if (isBatchKeyNew and k > 1) then
        redis.call("EXPIRE", batchKey, ARGV[5])
    end
end
LUA;
    }

    private static function getHashKeySuffix(): string
    {
        return date('Ymd_His');
    }

    private static function getOneTaskSeconds(): float|int
    {
        if (self::$oneTaskSeconds == 0) {
            //最低间隔，要在这个时间内执行掉全部任务
            $totalSeconds = get_setting("main.autoclean_interval_one");
            //每个任务能分到的秒数，不能到顶，任务数+1计算
            self::$oneTaskSeconds = floor($totalSeconds / (self::$totalTask + 1));
        }
        return self::$oneTaskSeconds;
    }

    private static function getDelayBase($taskIndex): float|int
    {
        return self::getOneTaskSeconds() * $taskIndex;
    }

    private static function getDelay(int $taskIndex, int $length, int $page): float
    {
        //超始基数
        $base = self::getDelayBase($taskIndex);
        //一共有这么多时间可以使用
        $totalSeconds = self::getOneTaskSeconds();
        //分几份
        $totalPage = ceil($length / self::$scanSize);
        //每份多长
        $perPage = floor($totalSeconds / $totalPage);
        //page 从 0 开始
        $offset = $page * $perPage;

        return floor($base + $offset);
    }

    private static function getCacheKeyLifeTime(): int
    {
        $four = self::getInterval("four");
        $three = self::getInterval("three");
        $one = self::getInterval("one");
        return intval($four) + intval($three) + intval($one);
    }

    private static function getInterval($level): int
    {
        $name = sprintf("main.autoclean_interval_%s", $level);
        return intval(get_setting($name));
    }

    public static function checkCleanup(): void
    {
        $now = Carbon::now();
        $timestamp = $now->getTimestamp();
        $toolRep = new ToolRepository();
        $arvToLevel = [
            "lastcleantime" => "one",
            "lastcleantime2" => "two",
            "lastcleantime3" => "three",
            "lastcleantime4" => "four",
            "lastcleantime5" => "five",
        ];
        $avps = Avp::query()->get()->keyBy("arg");
        foreach ($arvToLevel as $arg => $level) {
            /** @var NexusModel $value */
            $value = $avps->get($arg);
            $interval = self::getInterval($level);
            if ($interval <= 0) {
                do_log(sprintf("level: %s not set cleanup interval", $level), "error");
                continue;
            }
            $lastTime = 0;
            if ($value && $value->value_u) {
                $lastTime = $value->value_u;
            }
            if ($timestamp < $lastTime + $interval * 2) {
                continue;
            }
            $receiverUid = get_setting("system.alarm_email_receiver");
            do_log("receiverUid: $receiverUid");
            if (empty($receiverUid)) {
                $locale = Locale::getDefault();
                $subject = self::getAlarmEmailSubjectForCleanup($locale);
                $msg = self::getAlarmEmailBodyForCleanup($now, $level, $lastTime, $interval, $locale);
                do_log(sprintf("%s - %s", $subject, $msg), "error");
            } else {
                $receiverUidArr = preg_split("/\s+/", $receiverUid);
                $users = User::query()->whereIn("id", $receiverUidArr)->get(User::$commonFields);
                foreach ($users as $user) {
                    $locale = $user->locale;
                    $subject = self::getAlarmEmailSubjectForCleanup($locale);
                    $msg = self::getAlarmEmailBodyForCleanup($now, $level, $lastTime, $interval, $locale);
                    $result = $toolRep->sendMail($user->email, $subject, $msg);
                    do_log(sprintf("send msg: %s result: %s", $msg, var_export($result, true)), $result ? "info" : "error");
                }
            }
            return;
        }
    }

    private static function getAlarmEmailSubjectForCleanup(string|null $locale = null)
    {
        return nexus_trans("cleanup.alarm_email_subject", ["site_name" => get_setting("basic.SITENAME")], $locale);
    }

    private static function getAlarmEmailBodyForCleanup(Carbon $now, string $level, int $lastTime, int $interval, string|null $locale = null)
    {
        return  nexus_trans("cleanup.alarm_email_body", [
            "now_time" => $now->toDateTimeString(),
            "level" => $level,
            "last_time" => $lastTime > 0 ? Carbon::createFromTimestamp($lastTime)->toDateTimeString() : "",
            "elapsed_seconds" => $lastTime > 0 ? $now->getTimestamp() - $lastTime : "",
            "elapsed_seconds_human" => $lastTime > 0 ? mkprettytime($now->getTimestamp() - $lastTime) : "",
            "interval" => $interval,
            "interval_human" => mkprettytime($interval),
        ], $locale);
    }

    public static function checkQueueFailedJobs(): void
    {
        $now = Carbon::now();
        $since = $now->subHours(6)->toDateTimeString();
        $failedJobsTable = nexus_config("queue.failed.table");
        $failedJobsCount = NexusDB::table($failedJobsTable)->where("failed_at", ">=", $since)->count();
        if ($failedJobsCount == 0) {
            do_log(sprintf("no failed jobs since: %s", $since));
            return;
        }
        $receiverUid = get_setting("system.alarm_email_receiver");
        do_log("receiverUid: $receiverUid");
        $toolRep = new ToolRepository();
        if (empty($receiverUid)) {
            $locale = Locale::getDefault();
            $subject = self::getAlarmEmailSubjectForQueueFailedJobs($locale);
            $msg = self::getAlarmEmailBodyForQueueFailedJobs($since, $failedJobsCount, $failedJobsTable, $locale);
            do_log(sprintf("%s - %s", $subject, $msg), "error");
        } else {
            $receiverUidArr = preg_split("/\s+/", $receiverUid);
            $users = User::query()->whereIn("id", $receiverUidArr)->get(User::$commonFields);
            foreach ($users as $user) {
                $locale = $user->locale;
                $subject = self::getAlarmEmailSubjectForQueueFailedJobs($locale);
                $msg = self::getAlarmEmailBodyForQueueFailedJobs($since, $failedJobsCount, $failedJobsTable, $locale);
                $result = $toolRep->sendMail($user->email, $subject, $msg);
                do_log(sprintf("send msg: %s result: %s", $msg, var_export($result, true)), $result ? "info" : "error");
            }
        }
    }

    private static function getAlarmEmailSubjectForQueueFailedJobs(string|null $locale = null)
    {
        return nexus_trans("cleanup.alarm_email_subject_for_queue_failed_jobs", ["site_name" => get_setting("basic.SITENAME")], $locale);
    }

    private static function getAlarmEmailBodyForQueueFailedJobs(string $since, int $count, string $failedJobTable, string|null $locale = null)
    {
        return  nexus_trans("cleanup.alarm_email_body_for_queue_failed_jobs", [
            "since" => $since,
            "count" => $count,
            "failed_job_table" => $failedJobTable,
        ], $locale);
    }
}
