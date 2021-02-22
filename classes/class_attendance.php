<?php
class Attendance
{
    protected $userid;
    protected $curdate;
    public function __construct($userid){
        $this->userid = $userid;
        $this->curdate = date('Y-m-d');
        $this->cachename = sprintf('attendance_%u_%s', $this->userid, $this->curdate);
    }

    public function check($flush = false)
    {
        global $Cache;
        if($flush || ($row = $Cache->get_value($this->cachename)) === false){
            $res = sql_query(sprintf('SELECT * FROM `attendance` WHERE `uid` = %u AND DATE(`added`) = %s', $this->userid, sqlesc($this->curdate.' 00:00:00'))) or sqlerr(__FILE__,__LINE__);
            $row = mysql_num_rows($res) ? mysql_fetch_assoc($res) : array();
            $Cache->cache_value($this->cachename, $row, 86400);
        }
        return empty($row) ? false : $row;
    }

    public function attend($initial = 10, $step = 5, $maximum = 2000, $continous = array())
    {
        if($this->check(true)) return false;
        $count = get_row_count('attendance', sprintf('WHERE `uid` = %u', $this->userid));
        $points = min($initial + $step * $count, $maximum);
        $res = sql_query(sprintf('SELECT DATEDIFF(%s, `added`) AS diff, `days` FROM `attendance` WHERE `uid` = %u ORDER BY `id` DESC LIMIT 1', sqlesc($this->curdate), $this->userid)) or sqlerr(__FILE__,__LINE__);
        list($datediff, $days) = mysql_num_rows($res) ? mysql_fetch_row($res) : array('diff' => 0, 'days' => 0);
        $cdays = $datediff == 1 ? ++$days : 1;
        if($cdays > 1){
            krsort($continous);
            foreach($continous as $sday => $svalue){
                if($cdays >= $sday){
                    $points += $svalue;
                    break;
                }
            }
        }
        sql_query(sprintf('INSERT INTO `attendance` (`uid`,`added`,`points`,`days`) VALUES (%u, %s, %u, %u)', $this->userid, sqlesc(date('Y-m-d H:i:s')), $points, $cdays)) or sqlerr(__FILE__, __LINE__);
        KPS('+', $points, $this->userid);
        global $Cache;
        $Cache->delete_value($this->cachename);
        return array(++$count, $cdays, $points);
    }
}