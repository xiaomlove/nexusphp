<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nexus\Database\NexusDB;

class CalculateUserSeedBonus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $beginUid;

    private int $endUid;

    private string $uidArrStr;

    private string $requestId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $beginUid, int $endUid, string $uidArrStr, string $requestId = '')
    {
        $this->beginUid = $beginUid;
        $this->endUid = $endUid;
        $this->uidArrStr = $uidArrStr;
        $this->requestId = $requestId;
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        return now()->addSeconds(Setting::get('main.autoclean_interval_one'));
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
        $logPrefix = sprintf("[CLEANUP_CLI_CALCULATE_SEED_BONUS_HANDLE_JOB], commonRequestId: %s, beginUid: %s, endUid: %s", $this->requestId, $this->beginUid, $this->endUid);
//        $sql = sprintf("select userid from peers where userid > %s and userid <= %s and seeder = 'yes' group by userid", $this->beginUid, $this->endUid);
//        $results = NexusDB::select($sql);
//        $count = count($results);
//        do_log("$logPrefix, [GET_UID], sql: $sql, count: " . count($results));
//        if ($count == 0) {
//            do_log("$logPrefix, no user...");
//            return;
//        }
        $haremAdditionFactor = Setting::get('bonus.harem_addition');
        $officialAdditionFactor = Setting::get('bonus.official_addition');
        $donortimes_bonus = Setting::get('bonus.donortimes');
        $autoclean_interval_one = Setting::get('main.autoclean_interval_one');
        $sql = sprintf("select %s from users where id in (%s)", implode(',', User::$commonFields), $this->uidArrStr);
        $results = NexusDB::select($sql);
        $logFile = getLogFile("seed-bonus-points");
        do_log("$logPrefix, [GET_UID_REAL], count: " . count($results) . ", logFile: $logFile");
        $fd = fopen($logFile, 'a');
        foreach ($results as $userInfo)
        {
            $uid = $userInfo['id'];
            $isDonor = is_donor($userInfo);
            $seedBonusResult = calculate_seed_bonus($uid);
            $bonusLog = "[CLEANUP_CLI_CALCULATE_SEED_BONUS_HANDLE_USER], user: $uid, seedBonusResult: " . nexus_json_encode($seedBonusResult);
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
            $sql = "update users set seed_points = ifnull(seed_points, 0) + $seed_points, seed_points_per_hour = {$seedBonusResult['seed_points']}, seedbonus = seedbonus + $all_bonus, seed_points_updated_at = '$updatedAt' where id = $uid limit 1";
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
        }
        $costTime = time() - $beginTimestamp;
        do_log("$logPrefix, [DONE], cost time: $costTime seconds");
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
