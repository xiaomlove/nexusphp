<?php
namespace App\Repositories;

use App\Models\Attendance;
use App\Models\AttendanceLog;
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
        $isUpdated = 1;
        $initialData = [
            'uid' => $uid,
            'added' => $now,
            'points' => $initialBonus,
            'days' => 1,
            'total_days' => 1,
        ];
        $update = $initialData;
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
                    $continuousDays = $this->getContinuousDays($attendance, Carbon::yesterday());
                    $points = $this->getContinuousPoints($continuousDays + 1);
                    do_log("[CONTINUOUS] continuous days from yesterday: $continuousDays, points: $points");
                    $update = [
                        'added' => $now,
                        'points' => $points,
                        'days' => $continuousDays + 1,
                        'total_days' => $attendance->total_days + 1,
                    ];
                } else {
                    //not continuous
                    do_log("[NOT_CONTINUOUS]");
                    $update['total_days'] = $attendance->total_days + 1;
                }
                do_log("[DO_UPDATE]: " . nexus_json_encode($update));
                $attendance->update($update);
            }
        }
        if ($isUpdated) {
            User::query()->where('id', $uid)->increment('seedbonus', $update['points']);
            $attendanceLog = [
                'uid' => $attendance->uid,
                'points' => $update['points'],
                'date' => $now->format('Y-m-d'),
            ];
            AttendanceLog::query()->insert($attendanceLog);
        }
        $attendance->added_time = $now->toTimeString();
        $attendance->is_updated = $isUpdated;
        $baseQuery = AttendanceLog::query()->where('date', $today->format('Y-m-d'));
        $attendance->today_counts = (clone $baseQuery)->count();
        $myId = (clone $baseQuery)->where('uid', $uid)->first(['id'])->id;
        $attendance->my_ranking = (clone $baseQuery)->where('id', '<=', $myId)->count();
        do_log("[FINAL_ATTENDANCE]: " . $attendance->toJson());
        return $attendance;

    }

    public function getAttendance($uid, $date = '')
    {
        $query = Attendance::query()
            ->where('uid', $uid)
            ->orderBy('id', 'desc');
        if (!empty($date)) {
            $query->where('added', '>=', Carbon::parse($date)->startOfDay())
                ->where('added', '<=', Carbon::parse($date)->endOfDay());
        }
        return $query->first();
    }

    public function getContinuousPoints($days)
    {
        $settings = Setting::get('bonus');
        $initial = $settings['attendance_initial'] ?? Attendance::INITIAL_BONUS;
        $step = $settings['attendance_step'] ?? Attendance::STEP_BONUS;
        $max = $settings['attendance_max'] ?? Attendance::MAX_BONUS;
        $extraAwards = $settings['attendance_continuous'] ?? Attendance::CONTINUOUS_BONUS;
        $points = min($initial + ($days - 1) * $step, $max);
        krsort($extraAwards);
        foreach ($extraAwards as $key => $value) {
            if ($days == $key) {
                $points += $value;
                break;
            }
        }
        return $points;
    }

    /**
     * 将旧的 1 人 1 天 1 条迁移到新版 1 人一条
     *
     * @return int
     */
    public function migrateAttendance(): int
    {
        $page = 1;
        $size = 10000;
        $caseWhens = [];
        $idArr = [];
        $table = 'attendance';
        while (true) {
            $logPrefix = "[MIGRATE_ATTENDANCE], page: $page, size: $size";
            //as soon as possible, don't use eloquent
            $result = NexusDB::table($table)
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
                $caseWhens[] = sprintf('when %s then %s', $row->id, $row->counts);
                $idArr[] = $row->id;
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
        $result = NexusDB::table($table)
            ->whereIn('id', $idArr)
            ->update(['total_days' => NexusDB::raw($caseWhenStr)]);

        do_log("[MIGRATE_ATTENDANCE] DONE! $caseWhenStr, result: " . var_export($result, true));

        return count($idArr);
    }

    /**
     * 清理签到记录，每人只保留一条
     *
     * @return int
     */
    public function cleanup(): int
    {
        $query = Attendance::query()->groupBy('uid')->havingRaw("count(*) > 1")->selectRaw('uid, max(id) as max_id');
        $page = 1;
        $size = 10000;
        $deleteCounts = 0;
        while (true) {
            $rows = $query->forPage($page, $size)->get();
            $log = "sql: " . last_query() . ", count: " . $rows->count();
            do_log($log, 'info', isRunningInConsole());
            if ($rows->isEmpty()) {
                $log = "no more data....";
                do_log($log, 'info', isRunningInConsole());
                break;
            }
            foreach ($rows as $row) {
                do {
                    $deleted = Attendance::query()
                        ->where('uid', $row->uid)
                        ->where('id', '<', $row->max_id)
                        ->limit(10000)
                        ->delete();
                    $log = "delete: $deleted by sql: " . last_query();
                    $deleteCounts += $deleted;
                    do_log($log, 'info', isRunningInConsole());
                } while ($deleted > 0);
            }
            $page++;
        }
        return $deleteCounts;
    }

    /**
     * 为 1.7 新的补签功能回写当前连续签到记录
     *
     * @param int $uid
     * @return int
     */
    public function migrateAttendanceLogs($uid = 0): int
    {
        $cleanUpCounts = $this->cleanup();
        do_log("cleanup count: $cleanUpCounts", 'info', isRunningInConsole());

        $page = 1;
        $size = 10000;
        $insert = [];
        $table = 'attendance_logs';
        while (true) {
            $logPrefix = "[MIGRATE_ATTENDANCE_LOGS], page: $page, size: $size";
            $query = Attendance::query()
                ->where('added', '>=', Carbon::yesterday())
                ->forPage($page, $size);
            if ($uid) {
                $query->where('uid', $uid);
            }
            $result = $query->get();
            do_log("$logPrefix, " . last_query() . ", count: " . $result->count(), 'info', isRunningInConsole());
            if ($result->isEmpty()) {
                do_log("$logPrefix, no more data...");
                break;
            }
            foreach ($result as $row) {
                $interval =\DateInterval::createFromDateString("-1 day");
                $period = new \DatePeriod($row->added->addDay(1), $interval, $row->days, \DatePeriod::EXCLUDE_START_DATE);
                $i = 0;
                foreach ($period as $periodValue) {
                    $insert[] = sprintf(
                        "(%d, %d, '%s')",
                        $row->uid, $i == 0 ? $row->points : 0, $periodValue->format('Y-m-d')
                    );
                    $i++;
                }
            }
            $page++;
        }
        if (empty($insert)) {
            do_log("no data to insert...", 'info', isRunningInConsole());
            return 0;
        }
        $sql = sprintf(
            "insert into `%s` (`uid`, `points`, `date`) values %s on duplicate key update `uid` = values(`uid`)",
            $table, implode(',', $insert)
        );
        NexusDB::statement($sql);
        $insertCount = count($insert);
        do_log("[MIGRATE_ATTENDANCE_LOGS] DONE! insert sql: " . $sql, 'info', isRunningInConsole());

        return $insertCount;
    }

    public function getContinuousDays(Attendance $attendance, $start): int
    {
        $start = Carbon::parse($start);
        $logQuery = $attendance->logs()->where('date', '<=', $start->format('Y-m-d'))->orderBy('date', 'desc');
        $attendanceLogs = $logQuery->get(['date'])->keyBy('date');
        $counts = $attendanceLogs->count();
        do_log(sprintf('user: %s, log counts: %s from query: %s', $attendance->uid, $counts, last_query()));
        if ($counts == 0) {
            return 0;
        }
        $interval =\DateInterval::createFromDateString("-1 day");
        $period = new \DatePeriod($start->clone()->addDay(1), $interval, $counts, \DatePeriod::EXCLUDE_START_DATE);
        $days = 0;
        foreach ($period as $value) {
            $checkDate = $value->format('Y-m-d');
            if ($attendanceLogs->has($checkDate)) {
                $days++;
                do_log(sprintf('user: %s, date: %s, [HAS_ATTENDANCE], now days: %s', $attendance->uid, $checkDate, $days));
            } else {
                do_log(sprintf('user: %s, date: %s, [NOT_ATTENDANCE], now days: %s', $attendance->uid, $checkDate, $days));
                break;
            }
        }
        return $days;

    }

    public function retroactive($user, $timestampMs)
    {
        if (!$user instanceof User) {
            $user = User::query()->findOrFail((int)$user);
        }
        $attendance = $this->getAttendance($user->id);
        if (!$attendance) {
            throw new \LogicException(nexus_trans('attendance.have_not_attendance_yet'));
        }
        $date = Carbon::createFromTimestampMs($timestampMs);
        $now = Carbon::now();
        if ($date->gte($now) || $now->diffInDays($date) > Attendance::MAX_RETROACTIVE_DAYS) {
            throw new \LogicException(nexus_trans('attendance.target_date_can_no_be_retroactive', ['date' => $date->format('Y-m-d')]));
        }
        return NexusDB::transaction(function () use ($user, $attendance, $date) {
            if (AttendanceLog::query()->where('uid', $user->id)->where('date', $date->format('Y-m-d'))->exists()) {
                throw new \RuntimeException(nexus_trans('attendance.already_attendance'));
            }
            if ($user->attendance_card < 1) {
                throw new \RuntimeException(nexus_trans('attendance.card_not_enough'));
            }
            $log = sprintf('user: %s, card: %s, retroactive date: %s', $user->id, $user->attendance_card, $date->format('Y-m-d'));
            $continuousDays = $this->getContinuousDays($attendance, $date->clone()->subDays(1));
            $log .= ", continuousDays from prev day: $continuousDays";
            $points = $this->getContinuousPoints($continuousDays + 1);
            $log .= ", points: $points";
            do_log($log);
            $userUpdates = [
                'attendance_card' => NexusDB::raw('attendance_card - 1'),
                'seedbonus' => NexusDB::raw("seedbonus + $points"),
            ];
            $affectedRows = User::query()
                ->where('id', $user->id)
                ->where('attendance_card', $user->attendance_card)
                ->update($userUpdates);
            $msg = "Decrement user attendance_card and increment bonus";
            if ($affectedRows != 1) {
                do_log("$msg fail, query: " . last_query());
                throw new \RuntimeException("$msg fail");
            }
            do_log("$msg success, query: " . last_query());
            $insert = [
                'uid' => $user->id,
                'points' => $points,
                'date' => $date,
                'is_retroactive' => 1,
            ];
            $attendanceLog = AttendanceLog::query()->create($insert);
            //Increment total days and update days.
            $attendance->update([
                'total_days' => NexusDB::raw('total_days + 1'),
                'days' => $this->getContinuousDays($attendance, Carbon::today()),
            ]);
            return $attendanceLog;
        });
    }
}
