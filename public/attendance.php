<?php
require '../include/bittorrent.php';
dbconn();
require get_langfile_path();
loggedinorreturn();
parked();
//$desk = new Attendance($CURUSER['id']);
//
//if($result = $desk->attend($attendance_initial_bonus, $attendance_step_bonus, $attendance_max_bonus, $attendance_continuous_bonus)){
//	list($count, $cdays, $points) = $result;
//	stdhead($lang_attendance['title']);
//	begin_main_frame();
//	begin_frame($lang_attendance['success']);
//	printf('<p>'.$lang_attendance['attend_info'].'</p>', $count, $cdays, $points);
//	end_frame();
//	echo '<ul>';
//	printf('<li>'.$lang_attendance['initial'].'</li>', $attendance_initial_bonus);
//	printf('<li>'.$lang_attendance['steps'].'</li>', $attendance_step_bonus, $attendance_max_bonus);
//	echo '<li><ol>';
//	foreach($attendance_continuous_bonus as $day => $value){
//		printf('<li>'.$lang_attendance['continuous'].'</li>', $day, $value);
//	}
//	echo '</ol></li>';
//	echo '</ul>';
//	end_main_frame();
//	stdfoot();
//}else{
//	stderr($lang_attendance['sorry'], $lang_attendance['already_attended']);
//}

\Nexus\Nexus::css('vendor/fullcalendar-5.10.2/main.min.css', 'header', true);
\Nexus\Nexus::js('vendor/fullcalendar-5.10.2/main.min.js', 'footer', true);

$lang = get_langfolder_cookie();
$localesMap = [
    'en' => 'en-us',
    'chs' => 'zh-cn',
    'cht' => 'zh-tw',
];
$localeJs = $localesMap[$lang] ?? 'en-us';
\Nexus\Nexus::js("vendor/fullcalendar-5.10.2/locales/{$localeJs}.js", 'footer', true);

$today = \Carbon\Carbon::today();
$tomorrow = \Carbon\Carbon::tomorrow();
$end = $today->clone()->endOfMonth();
$start = $today->clone()->subMonth(2);
$rep = new \App\Repositories\AttendanceRepository();
$attendance = $rep->attend($CURUSER['id']);
$logs = $attendance->logs()->where('date', '>=', $start->format('Y-m-d'))->get()->keyBy('date');
$interval = new \DateInterval('P1D');
$period = new \DatePeriod($start, $interval, $end);
$events = [];
foreach ($period as $value) {
    if ($value->gte($tomorrow)) {
        continue;
    }
    $checkDate = $value->format('Y-m-d');
    $eventBase = ['start' => $checkDate, 'end' => $checkDate];
    if ($logs->has($checkDate)) {
        $logValue = $logs->get($checkDate);
        $events[] = array_merge($eventBase, ['display' => 'background']);
        if ($logValue->points > 0) {
            $events[] = array_merge($eventBase, ['title' => $logValue->points]);
        }
        if ($logValue->is_retroactive) {
            $events[] = array_merge($eventBase, ['title' => $lang_attendance['retroactive_event_text'], 'display' => 'list-item']);
        }
    } elseif ($value->lte($today) && $value->diffInDays($today) <= \App\Models\Attendance::MAX_RETROACTIVE_DAYS) {
        $events[] = array_merge($eventBase, ['groupId' => 'to_do', 'display' => 'list-item']);
    }
}
$eventStr = json_encode($events);
$validRangeStr = json_encode(['start' => $start->format('Y-m-d'), 'end' => $end->clone()->addDays(1)->format('Y-m-d')]);

$js = <<<EOP
let events = JSON.parse('$eventStr')
let validRange = JSON.parse('$validRangeStr')
let confirmText = "{$lang_attendance['retroactive_confirm_tip']}"
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      locale: '$localeJs',
      events: events,
      validRange: validRange,
      eventClick: function(info) {
        console.log(info.event);
        if (info.event.groupId == 'to_do') {
            retroactive(info.event.start)
        }
      }
    });
    calendar.render();
});

function retroactive(start) {
    let year = start.getFullYear()
    let month = start.getMonth() + 1
    let day = start.getDate()
    let date = year + '-' + month + '-' + day
    if (!window.confirm(confirmText + date + ' ?')) {
        console.log("cancel")
        return
    }
    jQuery.post('ajax.php', {params: {timestamp: start.getTime()}, action: 'attendanceRetroactive'}, function (response) {
        console.log(response);
        if (response.ret != 0) {
            alert(response.msg)
        } else {
            location.reload();
        }
    }, 'json')
}
EOP;

\Nexus\Nexus::js($js, 'footer', false);

if (1) {
    $count = $attendance->total_days;
    $cdays = $attendance->days;
    $points = $attendance->points;

    stdhead($lang_attendance['title']);
    begin_main_frame();
    begin_frame($lang_attendance['success']);
    $headerLeft = sprintf($lang_attendance['attend_info'].$lang_attendance['retroactive_description'], $count, $cdays, $points, $CURUSER['attendance_card']);
    $headerRight = nexus_trans('attendance.ranking', ['ranking' => $attendance->my_ranking, 'counts' => $attendance->today_counts]);
    printf('<p>%s<span style="float:right">%s</span></p>', $headerLeft, $headerRight);
    end_frame();
    echo '<div style="display: flex;justify-content: center;padding: 20px 0"><div id="calendar" style="width: 60%"></div></div>';
    echo '<ul>';
    printf('<li>'.$lang_attendance['initial'].'</li>', $attendance_initial_bonus);
    printf('<li>'.$lang_attendance['steps'].'</li>', $attendance_step_bonus, $attendance_max_bonus);
    echo '<li><ol>';
    foreach($attendance_continuous_bonus as $day => $value){
        printf('<li>'.$lang_attendance['continuous'].'</li>', $day, $value);
    }
    echo '</ol></li>';
    echo '</ul>';
    end_main_frame();
    stdfoot();

} else {
    stderr($lang_attendance['sorry'], $lang_attendance['already_attended']);
}
