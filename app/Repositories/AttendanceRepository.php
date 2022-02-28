<?php
namespace App\Repositories;

use App\Models\Attendance;
use App\Models\Setting;
use Carbon\Carbon;

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
}
