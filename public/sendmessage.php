<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
parked();

	$receiver = intval($_GET["receiver"] ?? 0);
	int_check($receiver,true);

	$replyto = $_GET["replyto"] ?? '';
	if ($replyto && !is_valid_id($replyto))
		stderr($lang_sendmessage['std_error'],$lang_sendmessage['std_permission_denied']);

	$res = sql_query("SELECT * FROM users WHERE id=$receiver");
	$user = mysql_fetch_assoc($res);
	if (!$user)
		stderr($lang_sendmessage['std_error'],$lang_sendmessage['std_no_user_id']);
	$subject = "";
	$body = "";
	if ($replyto)
	{
		$res = sql_query("SELECT * FROM messages WHERE id=$replyto") or sqlerr();
		$msga = mysql_fetch_assoc($res);
		if ($msga["receiver"] != $CURUSER["id"])
			stderr($lang_sendmessage['std_error'],$lang_sendmessage['std_permission_denied']);
		$res = sql_query("SELECT username FROM users WHERE id=" . $msga["sender"]) or sqlerr();
		$usra = mysql_fetch_assoc($res);
		$body .= $msga['msg']."\n\n-------- [url=userdetails.php?id=".$CURUSER["id"]."]".$CURUSER["username"]."[/url][i] Wrote at ".date("Y-m-d H:i:s").":[/i] --------\n";
		$subject = $msga['subject'];
		if (preg_match('/^Re:\s/', $subject))
			$subject = preg_replace('/^Re:\s(.*)$/', 'Re(2): \\1', $subject);
		elseif (preg_match('/^Re\([0-9]*\):\s/', $msga['subject']))
		{
			$replycount=(int)preg_replace('/^Re\(([0-9]*)\):\s/', '\\1', $subject);
			$replycount++;
			$subject=preg_replace('/^Re\(([0-9]*)\):\s(.*)$/', 'Re('.$replycount.'): \\2', $subject);
		}
		else $subject = "Re: " . $msga['subject'];
		$subject = htmlspecialchars($subject);
	}
	stdhead($lang_sendmessage['head_send_message'], false);
	begin_main_frame();
	print("<form id=compose name=\"compose\" method=post action=takemessage.php>");
	print("<input type=hidden name=receiver value=".$receiver.">");
	if ((isset($_GET["returnto"]) && $_GET["returnto"]) || $_SERVER["HTTP_REFERER"])
		print("<input type=hidden name=returnto value=\"".(htmlspecialchars($_GET["returnto"] ?? '') ? htmlspecialchars($_GET["returnto"]) : htmlspecialchars($_SERVER["HTTP_REFERER"]))."\">");
	$title = $lang_sendmessage['text_message_to'].get_username($receiver);
	begin_compose($title, ($replyto ? "reply" : "new"), $body, true, $subject);
	print("<tr><td class=toolbox colspan=2 align=center>");
	if ($replyto) {
		print("<input type=checkbox name='delete' value='yes' ".($CURUSER['deletepms'] == 'yes' ? " checked" : "").">".$lang_sendmessage['checkbox_delete_message_replying_to']."<input type=hidden name=origmsg value=".$replyto.">");
	}

	print("<input type=checkbox name='save' value='yes' ". ($CURUSER['savepms'] == 'yes' ? " checked" : "").">".$lang_sendmessage['checkbox_save_message_to_sendbox']);
	print("</td></tr>");
	end_compose();
	end_main_frame();
	stdfoot();
?>
