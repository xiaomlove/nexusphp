<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
if (!mkglobal("type"))
	die();

if ($type == "adminactivate") 
{
	stdhead($lang_ok['head_user_signup']);
	stdmsg($lang_ok['std_account_activated'],
	$lang_ok['account_activated_note']);
}
elseif ($type == "inviter") 
{
	stdhead($lang_ok['head_user_signup']);
	stdmsg($lang_ok['std_account_activated'],
	$lang_ok['account_activated_note_two']);
}
elseif ($type == "signup" && mkglobal("email")) 
{
	stdhead($lang_ok['head_user_signup']);
        stdmsg($lang_ok['std_signup_successful'],
	$lang_ok['std_confirmation_email_note']. htmlspecialchars($email) . $lang_ok['std_confirmation_email_note_end']);
	stdfoot();
}
elseif ($type == "sysop") {
		stdhead($lang_ok['head_sysop_activation']);
		print($lang_ok['std_sysop_activation_note']);
	if (isset($CURUSER))
		print($lang_ok['std_auto_logged_in_note']);
	else
		print($lang_ok['std_cookies_disabled_note']);
	stdfoot();
	}
elseif ($type == "confirmed") {
	stdhead($lang_ok['head_already_confirmed']);
	print($lang_ok['std_already_confirmed']);
	print($lang_ok['std_already_confirmed_note']);
	stdfoot();
}
elseif ($type == "confirm") {
	if (isset($CURUSER)) {
		stdhead($lang_ok['head_signup_confirmation']);
		print($lang_ok['std_account_confirmed']);
		print($lang_ok['std_auto_logged_in_note']);
		print($lang_ok['std_read_rules_faq']);
		stdfoot();
	}
	else {
		stdhead($lang_ok['head_signup_confirmation']);
		print($lang_ok['std_account_confirmed']);
		print($lang_ok['std_cookies_disabled_note']);
		stdfoot();
	}
}
else
	die();

?>
