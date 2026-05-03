<?php
require "../include/bittorrent.php";
if ($_SERVER["REQUEST_METHOD"] != "POST")
	stderr("Error", "Permission denied!");
dbconn();
loggedinorreturn();

if (get_user_class() < UC_SYSOP)
	stderr("Sorry", "Permission denied.");

$sender_id = ($_POST['sender'] == 'system' ? 0 : (int)$CURUSER['id']);
$dt = date("Y-m-d H:i:s");
$msg = trim($_POST['msg']);
$amount = $_POST['amount'];
if (!$msg || !$amount)
	stderr("Error","Don't leave any fields blank.");
if(!is_numeric($amount))
	stderr("Error","amount must be numeric");
$updateset = $_POST['clases'];
if (is_array($updateset)) {
	foreach ($updateset as $class) {
		if (!is_valid_id($class) && $class != 0)
			stderr("Error","Invalid Class");
	}
}else{
	if (!is_valid_id($updateset) && $updateset != 0)
		stderr("Error","Invalid Class");
}
$subject = trim($_POST['subject']);
$classes = array_map('intval', is_array($updateset) ? $updateset : [$updateset]);
$userRows = \Nexus\Database\NexusDB::table('users')->whereIn('class', $classes)->select(['id'])->get();

$amount = (int) getsize_int($amount,"G");
\Nexus\Database\NexusDB::table('users')->whereIn('class', $classes)->update(['uploaded' => \Nexus\Database\NexusDB::raw('uploaded + ' . $amount)]);

foreach ($userRows as $dat)
{
	$dat = (array) $dat;
	\Nexus\Database\NexusDB::insert('messages', [
		'sender' => (int) $sender_id,
		'receiver' => (int) $dat['id'],
		'added' => (string) $dt,
		'subject' => (string) $subject,
		'msg' => (string) $msg,
	]);
}

header("Location: amountupload.php?sent=1");
?>
