<?php

use Nexus\Database\NexusDB;

class Attendance
{
    protected $userid;

    protected $curdate;

    protected $cachename;

    public function __construct($userid)
    {
        $this->userid = $userid;
        $this->curdate = date('Y-m-d');
        $this->cachename = sprintf('attendance_%u_%s', $this->userid, $this->curdate);
    }

    public function check($flush = false)
    {
        global $Cache;
        if ($flush || ($row = $Cache->get_value($this->cachename)) === false) {
            $found = NexusDB::table('attendance')
                ->where('uid', (int) $this->userid)
                ->whereRaw('DATE(`added`) = ?', [$this->curdate.' 00:00:00'])
                ->first();
            $row = $found ? (array) $found : [];
            $Cache->cache_value($this->cachename, $row, 600);
        }

        return empty($row) ? false : $row;
    }

    public function attend($initial = 10, $step = 5, $maximum = 2000, $continous = [])
    {
        do_log(json_encode(func_get_args()));
        if ($this->check(true)) {
            return false;
        }
        $existing = NexusDB::table('attendance')
            ->selectRaw('id, DATEDIFF(?, `added`) AS diff, `days`, `total_days`', [$this->curdate])
            ->where('uid', (int) $this->userid)
            ->orderByDesc('id')
            ->limit(1)
            ->first();
        $doUpdate = $existing !== null;
        if ($doUpdate) {
            $row = (array) $existing;
            do_log("uid: {$this->userid}, row: ".json_encode($row));
            $id = $row['id'];
            $datediff = $row['diff'];
            $days = $row['days'];
            $totalDays = $row['total_days'];
        } else {
            [$id, $datediff, $days, $totalDays] = [0, 0, 0, 0];
        }
        $points = min($initial + $step * $days, $maximum);
        $cdays = $datediff == 1 ? ++$days : 1;
        if ($cdays > 1) {
            krsort($continous);
            foreach ($continous as $sday => $svalue) {
                if ($cdays >= $sday) {
                    $points += $svalue;
                    break;
                }
            }
        }
        $now = date('Y-m-d H:i:s');
        if ($doUpdate) {
            $payload = [
                'added' => $now,
                'points' => (int) $points,
                'days' => (int) $cdays,
                'total_days' => (int) ($totalDays + 1),
            ];
            do_log(sprintf('uid: %s, date: %s, doUpdate: 1, action: update id=%d, payload: %s', $this->userid, $this->curdate, (int) $id, json_encode($payload)), 'notice');
            NexusDB::table('attendance')
                ->where('id', (int) $id)
                ->limit(1)
                ->update($payload);
        } else {
            $payload = [
                'uid' => (int) $this->userid,
                'added' => $now,
                'points' => (int) $points,
                'days' => (int) $cdays,
                'total_days' => (int) ($totalDays + 1),
            ];
            do_log(sprintf('uid: %s, date: %s, doUpdate: 0, action: insert, payload: %s', $this->userid, $this->curdate, json_encode($payload)), 'notice');
            NexusDB::insert('attendance', $payload);
        }
        KPS('+', $points, $this->userid);
        global $Cache;
        $Cache->delete_value($this->cachename);

        return [++$totalDays, $cdays, $points];
    }
}
