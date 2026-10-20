<?php

namespace App\Jobs;

use App\Models\BonusLogs;
use App\Models\IpLog;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\IpLogRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Nexus\Database\NexusDB;
use Nexus\Nexus;

class CalculateUserSeedBonus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $beginUid;

    private int $endUid;

    private string $idStr;

    private string $requestId;

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

    public $timeout = 120;

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
            "[CLEANUP_CLI_CALCULATE_SEED_BONUS_HANDLE_JOB], commonRequestId: %s, beginUid: %s, endUid: %s, idStr: %s, idRedisKey: %s",
            $this->requestId, $this->beginUid, $this->endUid, $this->idStr, $this->idRedisKey
        );
        do_log("$logPrefix, job start ...");

        $haremAdditionFactor = Setting::get('bonus.harem_addition');
        $baseAdditionFactor = floatval(Setting::get('bonus.base_factor') ?: 1);
        $donortimes_bonus = Setting::get('bonus.donortimes');
        $autoclean_interval_one = Setting::get('main.autoclean_interval_one');

        $idStr = $this->idStr;
        $delIdRedisKey = false;
        if (empty($idStr) && !empty($this->idRedisKey)) {
            $delIdRedisKey = true;
            $idStr = NexusDB::cache_get($this->idRedisKey);
        }
        if (empty($idStr)) {
            do_log("$logPrefix, no idStr or idRedisKey", "error");
            return;
        }
        $sql = sprintf("select %s from users where id in (%s)", implode(',', User::$commonFields), $idStr);
        $results = NexusDB::select($sql);
        if (empty($results)) {
            do_log("$logPrefix, no data from idStr: $idStr", "error");
            return;
        }
        $logFile = getLogFile("seed-bonus-points");
        do_log("$logPrefix, [GET_UID_REAL], count: " . count($results) . ", logFile: $logFile");
        $fd = fopen($logFile, 'a');
        $seedPointsUpdates = $seedPointsPerHourUpdates = $seedBonusPerHourUpdates = $seedBonusUpdates = [];
        $seedingTorrentCountUpdates = $seedingTorrentSizeUpdates = [];
        $logStr = "";
        $bonusLogInsert = [];
        foreach ($results as $userInfo)
        {
            $uid = $userInfo['id'];
            $isDonor = is_donor($userInfo);
            $seedBonusResult = calculate_seed_bonus($uid);
            $bonusLog = "[CLEANUP_CLI_CALCULATE_SEED_BONUS_HANDLE_USER], user: $uid, seedBonusResult: " . nexus_json_encode($seedBonusResult);
            $baseBonus = $seedBonusResult['seed_bonus'] * $baseAdditionFactor;
            $classAdditionFactor = get_seed_bonus_class_addition_factor($userInfo['class']);
            $classAddition = $baseBonus * $classAdditionFactor;
            $all_bonus = $basicBonus = $baseBonus + $classAddition;
            $oldValue = $userInfo['seedbonus'];
            $bonusLog .= ", baseFactor: $baseAdditionFactor, classAdditionFactor: $classAdditionFactor, classAddition: $classAddition, all_bonus: $all_bonus";
            $this->appendBonusLogInsert($bonusLogInsert, $uid, BonusLogs::BUSINESS_TYPE_SEEDING_BASIC, $oldValue, $basicBonus);
            $oldValue += $basicBonus;
            /**
             * BUG: can't add this, case when not include info in where condition $idStr will be reset to 0
             * // BUG: 不能添加这部分，case when 不包含某些 uid 的数据，而 $idStr 里面又有，会被重置为 0
             * // 而且 seed_points_per_hour, seeding count/size  这些也是要实时更新为0的，不能添加这个跳过。
             */
//            if ($all_bonus == 0) {
//                do_log("$bonusLog, all_bonus is zero, skip");
//                continue;
//            }
            if ($isDonor && $donortimes_bonus != 0) {
                $donorAddition = $baseBonus * $donortimes_bonus;
                $all_bonus += $donorAddition;
                $bonusLog .= ", isDonor, donortimes_bonus: $donortimes_bonus, all_bonus: $all_bonus";
                $this->appendBonusLogInsert($bonusLogInsert, $uid, BonusLogs::BUSINESS_TYPE_SEEDING_DONOR_ADDITION, $oldValue, $donorAddition);
                $oldValue += $donorAddition;
            }
            if (($seedBonusResult['official_mteam_percent'] ?? 0) > 0) {
                $officialAddition = $seedBonusResult['official_bonus'] * $baseAdditionFactor;
                $all_bonus += $officialAddition;
                $bonusLog .= ", officialFormula: mteam, officialPercent: {$seedBonusResult['official_mteam_percent']}, official_bonus: {$seedBonusResult['official_bonus']}, officialAddition: $officialAddition, all_bonus: $all_bonus";
                $this->appendBonusLogInsert($bonusLogInsert, $uid, BonusLogs::BUSINESS_TYPE_SEEDING_OFFICIAL_ADDITION, $oldValue, $officialAddition);
                $oldValue += $officialAddition;
            }
            if ($haremAdditionFactor > 0) {
                $haremBonus = calculate_harem_addition($uid);
                $haremAddition =  $haremBonus * $haremAdditionFactor;
                $all_bonus += $haremAddition;
                $bonusLog .= ", haremAdditionFactor: $haremAdditionFactor, haremBonus: $haremBonus, haremAddition: $haremAddition, all_bonus: $all_bonus";
                $this->appendBonusLogInsert($bonusLogInsert, $uid, BonusLogs::BUSINESS_TYPE_SEEDING_HAREM_ADDITION, $oldValue, $haremAddition);
                $oldValue += $haremAddition;
            }
            if ($seedBonusResult['medal_additional_factor'] > 0) {
                $medalCoefficient = $seedBonusResult['medal_addition_coefficient'] ?? 1;
                $medalAddition = $seedBonusResult['medal_bonus'] * $seedBonusResult['medal_additional_factor'] * $medalCoefficient;
                $all_bonus += $medalAddition;
                $bonusLog .= ", medalAdditionFactor: {$seedBonusResult['medal_additional_factor']}, medalCoefficient: $medalCoefficient, medalBonus: {$seedBonusResult['medal_bonus']}, medalAddition: $medalAddition, all_bonus: $all_bonus";
                $this->appendBonusLogInsert($bonusLogInsert, $uid, BonusLogs::BUSINESS_TYPE_SEEDING_MEDAL_ADDITION, $oldValue, $medalAddition);
                $oldValue += $medalAddition;
            }
            do_log($bonusLog);
            $dividend = 3600 / $autoclean_interval_one;
            $all_bonus = $all_bonus / $dividend;
            $seed_points = $seedBonusResult['seed_points'] / $dividend;
//            $updatedAt = now()->toDateTimeString();
//            $sql = "update users set seed_points = ifnull(seed_points, 0) + $seed_points, seed_points_per_hour = {$seedBonusResult['seed_points']}, seedbonus = seedbonus + $all_bonus, seed_points_updated_at = '$updatedAt' where id = $uid limit 1";
//            do_log("$bonusLog, query: $sql");
//            NexusDB::statement($sql);
            $seedPointsUpdates[] = sprintf("when %d then ifnull(seed_points, 0) + %f", $uid, $seed_points);
            $seedPointsPerHourUpdates[] = sprintf("when %d then %f", $uid, $seedBonusResult['seed_points']);
            $seedBonusPerHourUpdates[] = sprintf("when %d then %f", $uid, $seedBonusResult['seed_bonus']);
            $seedingTorrentCountUpdates[] = sprintf("when %d then %f", $uid, $seedBonusResult['torrent_peer_count']);
            $seedingTorrentSizeUpdates[] = sprintf("when %d then %f", $uid, $seedBonusResult['size']);
            $seedBonusUpdates[] = sprintf("when %d then seedbonus + %f", $uid, $all_bonus);
            if ($fd) {
                $log = sprintf(
                    '%s|%s|%s|%s|%s|%s|%s|%s',
                    date('Y-m-d H:i:s'), $uid,
                    $userInfo['seed_points'], number_format($seed_points, 1, '.', ''),  number_format($userInfo['seed_points'] + $seed_points, 1, '.', ''),
                    $userInfo['seedbonus'], number_format($all_bonus, 1, '.', ''),  number_format($userInfo['seedbonus'] + $all_bonus, 1, '.', '')
                );
//                fwrite($fd, $log . PHP_EOL);
                $logStr .= $log . PHP_EOL;
            } else {
                do_log("logFile: $logFile is not writeable!", 'error');
            }
        }
        $nowStr = now()->toDateTimeString();
        $sql = sprintf(
            "update users set seed_points = case id %s end, seed_points_per_hour = case id %s end, seed_bonus_per_hour = case id %s end, seedbonus = case id %s end, seeding_torrent_count = case id %s end, seeding_torrent_size = case id %s end, seed_points_updated_at = '%s' where id in (%s)",
            implode(" ", $seedPointsUpdates), implode(" ", $seedPointsPerHourUpdates), implode(" ", $seedBonusPerHourUpdates), implode(" ", $seedBonusUpdates), implode(" ", $seedingTorrentCountUpdates), implode(" ", $seedingTorrentSizeUpdates), $nowStr, $idStr
        );
        $result = NexusDB::statement($sql);
        if ($delIdRedisKey) {
            NexusDB::cache_del($this->idRedisKey);
        }
        if ($fd) {
            fwrite($fd, $logStr);
        }
        if (!empty($bonusLogInsert)) {
//            BonusLogs::query()->insert($bonusLogInsert);
            $this->insertIntoClickHouseBulk($bonusLogInsert);
        }
        $costTime = time() - $beginTimestamp;
        do_log(sprintf(
            "$logPrefix, [DONE], update user count: %s, result: %s, cost time: %s seconds",
            count($seedPointsUpdates), var_export($result, true), $costTime
        ));
        do_log("$logPrefix, sql: $sql", "debug");
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

    private function appendBonusLogInsert(array &$bonusLogInsert, int $uid, int $businessType, $oldValue, $delta): void
    {
        if ($delta > 0) {
            $bonusLogInsert[] = [
                'business_type' => $businessType,
                'uid' => $uid,
                'old_total_value' => $oldValue,
                'value' => $delta,
                'new_total_value' => $oldValue + $delta,
                'comment' => BonusLogs::$businessTypes[$businessType]['text'] ?? '',
                'created_at' => getDtMicro(),
            ];
        }
    }

    private function insertIntoClickHouseBulk(array $bonusLogInsert): void
    {
        if (!Setting::getIsRecordSeedingBonusLog()) {
            do_log("not enabled");
            return;
        }
        $host = config('clickhouse.connection.host');
        if (!$host) {
            do_log("clickhouse no host");
            return;
        }
        try {
            $client = app(\ClickHouseDB\Client::class);
            $fields = ['business_type', 'uid', 'old_total_value', 'value', 'new_total_value', 'comment', 'created_at'];
            $client->insert("bonus_logs", $bonusLogInsert, $fields);
            do_log("insertIntoClickHouseBulk done, created_at: {$bonusLogInsert[0]['created_at']}, count: " . count($bonusLogInsert));
        } catch (\Exception $e) {
            do_log($e->getMessage(), 'error');
        }
    }
}
