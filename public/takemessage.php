<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
require_once(get_langfile_path("",true));
loggedinorreturn();

if ($_SERVER["REQUEST_METHOD"] != "POST")
	stderr($lang_takemessage['std_error'], $lang_takemessage['std_permission_denied']);

	$origmsg = intval($_POST["origmsg"] ?? 0);
	$msg = trim($_POST["body"]);
	if (isset($_POST['forward']) && $_POST['forward'] == 1) //this is forwarding
	{
		if (!$origmsg)
			stderr($lang_takemessage['std_error'], $lang_takemessage['std_invalid_id']);
		$res = sql_query("SELECT * FROM messages WHERE id=" . sqlesc($origmsg) . " AND (receiver=" . sqlesc($CURUSER['id']) . " OR sender=" . sqlesc($CURUSER['id']) .") LIMIT 1") or sqlerr(__FILE__,__LINE__);
		$origmsgrow = mysql_fetch_assoc($res);
		if (!$origmsgrow)
			stderr($lang_takemessage['std_error'], $lang_takemessage['std_no_permission_forwarding']);
		if(!$_POST['to'])
			stderr($lang_takemessage['std_error'], $lang_takemessage['std_must_enter_username']);
		$receiver = get_user_id_from_name(trim($_POST['to']));
		if ($origmsgrow['sender'] == 0)
		{
			$origfrom = $lang_takemessage_target[get_user_lang($receiver)]['msg_system'];
		}
		else
		{
			$origmsgsendername = get_plain_username($origmsgrow['sender']);
			$origfrom = "[url=userdetails.php?id=".$origmsgrow['sender']."]".$origmsgsendername."[/url]";
		}
		$msg = "-------- ".$lang_takemessage_target[get_user_lang($receiver)]['msg_original_message_from'] . $origfrom . " --------\n" . $origmsgrow['msg']."\n\n".($msg ? "-------- [url=userdetails.php?id=".$CURUSER["id"]."]".$CURUSER["username"]."[/url][i] Wrote at ".date("Y-m-d H:i:s").":[/i] --------\n".$msg : "");
		
	}
	else
	{
		$receiver = intval($_POST["receiver"] ?? 0);
		if (!is_valid_id($receiver) || ($origmsg && !is_valid_id($origmsg)))
			stderr($lang_takemessage['std_error'],$lang_takemessage['std_invalid_id']);
		$bodyadd = "";
		if (!$msg)
			stderr($lang_takemessage['std_error'],$lang_takemessage['std_please_enter_something']);
	}
	$save = $_POST["save"];
	$returnto = $_POST["returnto"];

	// Anti Flood Code
	// This code ensures that a member can only send one PM every 10 seconds.
	if (get_user_class() < $staffmem_class) {
		if (strtotime($CURUSER['last_pm']) > (TIMENOW - 10))
		{
			$secs = 60 - (TIMENOW - strtotime($CURUSER['last_pm']));
			stderr($lang_takemessage['std_error'],$lang_takemessage['std_message_flooding_denied'].$secs.$lang_takemessage['std_before_sending_pm']);
		}
	}

	// Change
	$save = ($save == 'yes') ? "yes" : "no";
	// End of Change

	$res = sql_query("SELECT id,username,parked,email,acceptpms, notifs, UNIX_TIMESTAMP(last_access) as la FROM users WHERE id=".sqlesc($receiver)) or sqlerr(__FILE__, __LINE__);
	$user = mysql_fetch_assoc($res);
	if (!$user)
		stderr($lang_takemessage['std_error'], $lang_takemessage['std_user_not_exist']);

	//Make sure recipient wants this message
	if (get_user_class() < $staffmem_class)
	{
		if ($user["parked"] == "yes")
		stderr($lang_takemessage['std_refused'], $lang_takemessage['std_account_parked']);
		if ($user["acceptpms"] == "yes")
		{
			$res2 = sql_query("SELECT * FROM blocks WHERE userid=".sqlesc($receiver)." AND blockid=" . sqlesc($CURUSER["id"])) or sqlerr(__FILE__, __LINE__);
			if (mysql_num_rows($res2) == 1)
			stderr($lang_takemessage['std_refused'], $lang_takemessage['std_user_blocks_your_pms']);
		}
		elseif ($user["acceptpms"] == "friends")
		{
			$res2 = sql_query("SELECT * FROM friends WHERE userid=".sqlesc($receiver)." AND friendid=" . sqlesc($CURUSER["id"])) or sqlerr(__FILE__, __LINE__);
			if (mysql_num_rows($res2) != 1)
			stderr($lang_takemessage['std_refused'], $lang_takemessage['std_user_accepts_friends_pms']);
		}
		elseif ($user["acceptpms"] == "no")
		stderr($lang_takemessage['std_refused'], $lang_takemessage['std_user_blocks_all_pms']);
	}

	$subject = trim($_POST['subject']);
	sql_query("INSERT INTO messages (sender, receiver, added, msg, subject, saved, location) VALUES(" . sqlesc($CURUSER["id"]) . ", ".sqlesc($receiver).", '" . date("Y-m-d H:i:s") . "', " . sqlesc($msg) . ", " . sqlesc($subject) . ", " . sqlesc($save) . ", 1)") or sqlerr(__FILE__, __LINE__);
	$Cache->delete_value('user_'.$receiver.'_unread_message_count');
	$Cache->delete_value('user_'.$receiver.'_inbox_count');
	$Cache->delete_value('user_'.$CURUSER["id"].'_outbox_count');
	
	$msgid=mysql_insert_id();
	$date=date("Y-m-d H:i:s");
	// Update Last PM sent...
	sql_query("UPDATE users SET last_pm = NOW() WHERE id = ".sqlesc($CURUSER['id'])) or sqlerr(__FILE__, __LINE__);

	// Send notification email.
if ($emailnotify_smtp=='yes' && $smtptype != 'none'){
	$mystring = $user['notifs'];
	$findme  = '[pm]';
	$pos = strpos($mystring, $findme);
	if ($pos === false)
	$sm = false;
	else
	$sm = true;

	if ($sm)
	{

		$username = trim($CURUSER["username"]);
		$msg_receiver = trim($user["username"]);
		$prefix = get_protocol_prefix();
		
		$title = "$SITENAME ".$lang_takemessage_target[get_user_lang($user["id"])]['mail_received_pm_from'] . $username . "!";
		$body = <<<EOD
		{$lang_takemessage_target[get_user_lang($user["id"])]['mail_dear']}$msg_receiver,
		
		{$lang_takemessage_target[get_user_lang($user["id"])]['mail_you_received_a_pm']}
		
		{$lang_takemessage_target[get_user_lang($user["id"])]['mail_sender']}: $username
		{$lang_takemessage_target[get_user_lang($user["id"])]['mail_subject']}: $subject
		{$lang_takemessage_target[get_user_lang($user["id"])]['mail_date']}: $date
		
		{$lang_takemessage_target[get_user_lang($user["id"])]['mail_use_following_url']}<b><a href="javascript:void(null)" onclick="window.open('$prefix$BASEURL/messages.php?action=viewmessage&id=$msgid')">{$lang_takemessage_target[get_user_lang($user["id"])]['mail_here']}</a></b>{$lang_takemessage_target[get_user_lang($user["id"])]['mail_use_following_url_1']}<br />
$prefix$BASEURL/messages.php?action=viewmessage&id=$msgid
		
		------{$lang_takemessage_target[get_user_lang($user["id"])]['mail_yours']}
		{$lang_takemessage_target[get_user_lang($user["id"])]['mail_the_site_team']}
EOD;

		sent_mail($user["email"],$SITENAME,$SITEEMAIL,$title,str_replace("<br />","<br />",nl2br($body)),"sendmessage",false,false,'');

	}
}
	$delete = $_POST["delete"];

	if ($origmsg)
	{
		if ($delete == "yes")
		{
			// Make sure receiver of $origmsg is current user
			$res = sql_query("SELECT * FROM messages WHERE id=$origmsg") or sqlerr(__FILE__, __LINE__);
			if (mysql_num_rows($res) == 1)
			{
				$arr = mysql_fetch_assoc($res);
				if ($arr["receiver"] != $CURUSER["id"])
				stderr("w00t","This shouldn't happen.");
				if ($arr["saved"] == "no")
				sql_query("DELETE FROM messages WHERE id=$origmsg") or sqlerr(__FILE__, __LINE__);
				elseif ($arr["saved"] == "yes")
				sql_query("UPDATE messages SET location = '0' WHERE id=$origmsg") or sqlerr(__FILE__, __LINE__);

			}
		}
		if (!$returnto)
		$returnto = "" . get_protocol_prefix() . "$BASEURL/messages.php";
	}

	if ($returnto)
	{
		header("Location: $returnto");
		die;
	}

	stdhead();
	stdmsg($lang_takemessage['std_succeeded'], (($n_pms > 1) ? "$n".$lang_takemessage['std_messages_out_of']."$n_pms".$lang_takemessage['std_were'] : $lang_takemessage['std_message_was']).
	$lang_takemessage['std_successfully_sent'] . ($l ? " $l profile comment" . (($l>1) ? $lang_takemessage['std_s_were'] : $lang_takemessage['std_was']) . $lang_takemessage['std_updated'] : ""));
stdfoot();
exit;
?>
