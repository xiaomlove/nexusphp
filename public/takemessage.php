<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
//require_once(get_langfile_path("",true));
loggedinorreturn();

if ($_SERVER["REQUEST_METHOD"] != "POST")
	stderr($lang_takemessage['std_error'], $lang_takemessage['std_permission_denied']);

	$origmsg = intval($_POST["origmsg"] ?? 0);
	$msg = trim($_POST["body"]);
	if (isset($_POST['forward']) && $_POST['forward'] == 1) //this is forwarding
	{
		if (!$origmsg)
			stderr($lang_takemessage['std_error'], $lang_takemessage['std_invalid_id']);
		$origMsgRows = \Nexus\Database\NexusDB::select("SELECT * FROM messages WHERE id = " . (int) $origmsg . " AND (receiver = " . (int) $CURUSER['id'] . " OR sender = " . (int) $CURUSER['id'] . ") LIMIT 1");
		$origmsgrow = $origMsgRows[0] ?? null;
		if (!$origmsgrow)
			stderr($lang_takemessage['std_error'], $lang_takemessage['std_no_permission_forwarding']);
		if(!$_POST['to'])
			stderr($lang_takemessage['std_error'], $lang_takemessage['std_must_enter_username']);
		$receiver = get_user_id_from_name(trim($_POST['to']));
        $locale = get_user_locale($receiver);
		if ($origmsgrow['sender'] == 0)
		{
			$origfrom = nexus_trans("message.msg_system", [], $locale);
		}
		else
		{
			$origmsgsendername = get_plain_username($origmsgrow['sender']);
			$origfrom = "[url=userdetails.php?id=".$origmsgrow['sender']."]".$origmsgsendername."[/url]";
		}
		$msg = "-------- ".nexus_trans("message.msg_original_message_from", [], $locale) . $origfrom . " --------\n" . $origmsgrow['msg']."\n\n".($msg ? "-------- [url=userdetails.php?id=".$CURUSER["id"]."]".$CURUSER["username"]."[/url][i] Wrote at ".date("Y-m-d H:i:s").":[/i] --------\n".$msg : "");

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
	if (!user_can('staffmem')) {
		if (strtotime($CURUSER['last_pm']) > (TIMENOW - 10))
		{
			$secs = 60 - (TIMENOW - strtotime($CURUSER['last_pm']));
			stderr($lang_takemessage['std_error'],$lang_takemessage['std_message_flooding_denied'].$secs.$lang_takemessage['std_before_sending_pm']);
		}
	}

	// Change
	$save = ($save == 'yes') ? "yes" : "no";
	// End of Change

	$userRows = \Nexus\Database\NexusDB::select("SELECT id, username, parked, email, acceptpms, notifs, UNIX_TIMESTAMP(last_access) as la FROM users WHERE id = " . (int) $receiver);
	$user = $userRows[0] ?? null;
	if (!$user)
		stderr($lang_takemessage['std_error'], $lang_takemessage['std_user_not_exist']);

	//Make sure recipient wants this message
	if (!user_can('staffmem'))
	{
		if ($user["parked"] == "yes")
		stderr($lang_takemessage['std_refused'], $lang_takemessage['std_account_parked']);
		if ($user["acceptpms"] == "yes")
		{
			$blockRows = \Nexus\Database\NexusDB::select("SELECT id FROM blocks WHERE userid = " . (int) $receiver . " AND blockid = " . (int) $CURUSER["id"]);
			if (count($blockRows) == 1)
			stderr($lang_takemessage['std_refused'], $lang_takemessage['std_user_blocks_your_pms']);
		}
		elseif ($user["acceptpms"] == "friends")
		{
			$friendRows = \Nexus\Database\NexusDB::select("SELECT id FROM friends WHERE userid = " . (int) $receiver . " AND friendid = " . (int) $CURUSER["id"]);
			if (count($friendRows) != 1)
			stderr($lang_takemessage['std_refused'], $lang_takemessage['std_user_accepts_friends_pms']);
		}
		elseif ($user["acceptpms"] == "no")
		stderr($lang_takemessage['std_refused'], $lang_takemessage['std_user_blocks_all_pms']);
	}

	$subject = trim($_POST['subject']);

	$createdMessage = \App\Models\Message::add([
		'sender' => $CURUSER["id"],
		'receiver' => $receiver,
		'msg' => $msg,
		'subject' => $subject,
		'added' => now(),
		'saved' => $save,
		'location' => 1,
	]);

	$Cache->delete_value('user_'.$CURUSER["id"].'_outbox_count');

	$msgid = (int) $createdMessage->id;
	$date=date("Y-m-d H:i:s");
	// Update Last PM sent...
	\Nexus\Database\NexusDB::statement("UPDATE users SET last_pm = NOW() WHERE id = " . (int) $CURUSER['id']);

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
        $locale = get_user_locale($user['id']);
		$title = "$SITENAME ".nexus_trans("message.mail_received_pm_from", [], $locale) . $username . "!";
        $mailDear = nexus_trans("message.mail_dear", [], $locale);
        $mailYouReceivedAPm = nexus_trans("message.mail_you_received_a_pm", [], $locale);
        $mailSender = nexus_trans("message.mail_sender", [], $locale);
        $mailSubject = nexus_trans("message.mail_subject", [], $locale);
        $mailDate = nexus_trans("message.mail_date", [], $locale);
        $mailYouFollowingUrl = nexus_trans("message.mail_use_following_url", [], $locale);
        $mailHere = nexus_trans("message.mail_here", [], $locale);
        $mailYouFollowingUrl1 = nexus_trans("message.mail_use_following_url_1", [], $locale);
        $mailYours = nexus_trans("message.mail_yours", [], $locale);
        $siteName = \App\Models\Setting::getSiteName();
        $mailTheSiteTeam = sprintf(nexus_trans("message.mail_the_site_team", [], $locale), $siteName);
		$body = <<<EOD
		{$mailDear}$msg_receiver,

		{$mailYouReceivedAPm}

		{$mailSender}: $username
		{$mailSubject}: $subject
		{$mailDate}: $date

		{$mailYouFollowingUrl}<b><a href="javascript:void(null)" onclick="window.open('$prefix$BASEURL/messages.php?action=viewmessage&id=$msgid')">{$mailHere}</a></b>{$mailYouFollowingUrl1}<br />
$prefix$BASEURL/messages.php?action=viewmessage&id=$msgid

		------{$mailYours}
		{$mailTheSiteTeam}
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
			$origRows = \Nexus\Database\NexusDB::select("SELECT * FROM messages WHERE id = " . (int) $origmsg);
			if (count($origRows) == 1)
			{
				$arr = $origRows[0];
				if ($arr["receiver"] != $CURUSER["id"])
				stderr("w00t","This shouldn't happen.");
				if ($arr["saved"] == "no")
				\Nexus\Database\NexusDB::statement("DELETE FROM messages WHERE id = " . (int) $origmsg);
				elseif ($arr["saved"] == "yes")
				\Nexus\Database\NexusDB::statement("UPDATE messages SET location = '0' WHERE id = " . (int) $origmsg);

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
