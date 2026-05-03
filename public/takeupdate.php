<?php
require_once("../include/bittorrent.php");
function bark($msg) {
 stdhead();
   stdmsg("Failed", $msg);
 stdfoot();
 exit;
}
dbconn();
loggedinorreturn();
user_can('staffmem', true);
if (empty($_POST['delreport'])) {
    stderr('Error', $lang_functions['select_at_least_one_record']);
}
$reportIds = array_map('intval', (array) $_POST['delreport']);
if ($_POST['setdealt']){
\Nexus\Database\NexusDB::table('reports')->where('dealtwith', 0)->whereIn('id', $reportIds)->update([
	'dealtwith' => 1,
	'dealtby' => (int) $CURUSER['id'],
]);
	$Cache->delete_value('staff_new_report_count');
}
elseif ($_POST['delete']){
\Nexus\Database\NexusDB::table('reports')->whereIn('id', $reportIds)->delete();
	$Cache->delete_value('staff_new_report_count');
	$Cache->delete_value('staff_report_count');
}

header("Location: reports.php");
