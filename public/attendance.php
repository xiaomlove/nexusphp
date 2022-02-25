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

$rep = new \App\Repositories\AttendanceRepository();
$result = $rep->attend($CURUSER['id']);
if ($result->is_updated) {
    $count = $result->total_days;
    $cdays = $result->days;
    $points = $result->points;

    stdhead($lang_attendance['title']);
    begin_main_frame();
    begin_frame($lang_attendance['success']);
    printf('<p>'.$lang_attendance['attend_info'].'</p>', $count, $cdays, $points);
    end_frame();
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
