<?php
namespace App\Repositories;

use App\Models\Attendance;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Nexus\Database\NexusDB;

class AttendanceRepository extends BaseRepository
{
    public function attend($uid)
    {
        $attendance = $this->getAttendance($uid);
        $now = Carbon::now();
        $today = Carbon::today();
        $settings = Setting::get('bonus');
        $initialBonus = $settings['attendance_initial'] ?? Attendance::INITIAL_BONUS;
        $stepBonus = $settings['attendance_step'] ?? Attendance::STEP_BONUS;
        $maxBonus = $settings['attendance_max'] ?? Attendance::MAX_BONUS;
        $continuousBonus = $settings['attendance_continuous'] ?? Attendance::CONTINUOUS_BONUS;
        $isUpdated = 1;
        $initialData = [
            'uid' => $uid,
            'added' => $now,
            'points' => $initialBonus,
            'days' => 1,
            'total_days' => 1,
        ];
        if (!$attendance) {
            //first time
            do_log("[DO_INSERT]: " . nexus_json_encode($initialData));
            $attendance = Attendance::query()->create($initialData);
        } else {
            $added = $attendance->added->startOfDay();
            do_log("[ORIGINAL_DATA]: " . $attendance->toJson());
            if ($added->gte($today)) {
                //already attended today, do nothing
                $isUpdated = 0;
            } else {
                $diffDays = $today->diffInDays($added);
                if ($diffDays == 1) {
                    //yesterday do it, it's continuous
                    do_log("[CONTINUOUS]");
                    $points = $this->getContinuousPoints($initialBonus, $stepBonus, $attendance->days, $maxBonus, $continuousBonus);
                    $update = [
                        'added' => $now,
                        'points' => $points,
                        'days' => $attendance->days + 1,
                        'total_days' => $attendance->total_days + 1,
                    ];
                } else {
                    //not continuous
                    do_log("[NOT_CONTINUOUS]");
                    $update = $initialData;
                    $update['total_days'] = $attendance->total_days + 1;
                }
                do_log("[DO_UPDATE]: " . nexus_json_encode($update));
                $attendance->update($update);
                User::query()->where('id', $uid)->increment('seedbonus', $update['points']);
            }
        }
        $attendance->added_time = $now->toTimeString();
        $attendance->is_updated = $isUpdated;
        do_log("[FINAL_ATTENDANCE]: " . $attendance->toJson());
        return $attendance;

    }

    public function getAttendance($uid, $date = '')
    {
        $query = Attendance::query()
            ->where('uid', $uid)
            ->orderBy('id', 'desc');
        if (!empty($date)) {
            $query->where('added', '>=', Carbon::today())
                ->where('added', '<', Carbon::tomorrow());
        }
        return $query->first();
    }

    private function getContinuousPoints($initial, $step, $days, $max, $extraAwards)
    {
        $points = min($initial + $days * $step, $max);
        krsort($extraAwards);
        foreach ($extraAwards as $key => $value) {
            if ($days >= $key) {
                $points += $value;
                break;
            }
        }
        return $points;
    }

    public function migrateAttendance(): int
    {
        $page = 1;
        $size = 10000;
        $caseWhens = [];
        $idArr = [];
        while (true) {
            $logPrefix = "[MIGRATE_ATTENDANCE], page: $page, size: $size";
            $result = Attendance::query()
                ->groupBy(['uid'])
                ->selectRaw('uid, max(id) as id, count(*) as counts')
                ->forPage($page, $size)
                ->get();
            do_log("$logPrefix, " . last_query() . ", count: " . $result->count());
            if ($result->isEmpty()) {
                do_log("$logPrefix, no more data...");
                break;
            }
            foreach ($result as $row) {
                //use case when instead.
                $caseWhens[] = sprintf('when %s then %s', $row->id, $row->counts);
                $idArr[] = $row->id;
//                $update = [
//                    'total_days' => $row->counts,
//                ];
//                $updateResult = $row->update($update);
                do_log(sprintf(
                    "$logPrefix, update user: %s(ID: %s) => %s",
                    $row->uid, $row->id, $row->counts
                ));
            }
            $page++;
        }
        if (empty($caseWhens)) {
            do_log("no data to update...");
            return 0;
        }
        $caseWhenStr = sprintf('case id %s end', implode(' ', $caseWhens));
        $result = Attendance::query()
            ->whereIn('id', $idArr)
            ->update(['total_days' => NexusDB::raw($caseWhenStr)]);

        do_log("[MIGRATE_ATTENDANCE] DONE! $caseWhenStr, result: " . var_export($result, true));

        return count($idArr);
    }
}
