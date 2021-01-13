<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();

if ($_SERVER["REQUEST_METHOD"] != "POST")
	stderr($lang_takecontact['std_error'], $lang_takecontact['std_method']);

$msg = trim($_POST["body"]);
$subject = trim($_POST["subject"]);

if (!$msg)
	stderr($lang_takecontact['std_error'],$lang_takecontact['std_please_enter_something']);

if (!$subject)
	stderr($lang_takecontact['std_error'],$lang_takecontact['std_please_define_subject']);

$added = "'" . date("Y-m-d H:i:s") . "'";
$userid = $CURUSER['id'];
$message = sqlesc($msg);
$subject = sqlesc($subject);

// Anti Flood Code
// This code ensures that a member can only send one PM per minute.
if (get_user_class() < UC_MODERATOR) {
	if (strtotime($CURUSER['last_staffmsg']) > (TIMENOW - 60))
	{
		$secs = 60 - (TIMENOW - strtotime($CURUSER['last_staffmsg']));
		stderr($lang_takecontact['std_error'],$lang_takecontact['std_message_flooding'].$secs.$lang_takecontact['std_second'].($secs == 1 ? '' : $lang_takecontact['std_s']).$lang_takecontact['std_before_sending_pm']);
	}
}
sql_query("INSERT INTO staffmessages (sender, added, msg, subject) VALUES($userid, $added, $message, $subject)") or sqlerr(__FILE__, __LINE__);
// Update Last PM sent...
sql_query("UPDATE users SET last_staffmsg = NOW() WHERE id = ".sqlesc($CURUSER['id'])) or sqlerr(__FILE__, __LINE__);
$Cache->delete_value('staff_message_count');
$Cache->delete_value('staff_new_message_count');
if ($_POST["returnto"])
{
	header("Location: " . htmlspecialchars($_POST["returnto"]));
	die;
}

stdhead();
stdmsg($lang_takecontact['std_succeeded'], $lang_takecontact['std_message_succesfully_sent']);
stdfoot();
exit;
?>
