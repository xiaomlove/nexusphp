<?php
require "../include/bittorrent.php";
dbconn();
failedloginscheck ("Recover",true);
$take_recover = !isset($_GET['sitelanguage']);
$langid = intval($_GET['sitelanguage'] ?? 0);
$baseUrl = getSchemeAndHttpHost();
if ($langid)
{
	$lang_folder = validlang($langid);
	if(get_langfolder_cookie() != $lang_folder)
	{
		set_langfolder_cookie($lang_folder);
		header("Location: " . $_SERVER['PHP_SELF']);
	}
}
require_once(get_langfile_path("", false, $CURLANGDIR));

function bark($msg) {
	global $lang_recover;
	stdhead();
	stdmsg($lang_recover['std_recover_failed'], $msg);
	stdfoot();
	exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
	if ($iv == "yes")
	check_code ($_POST['imagehash'], $_POST['imagestring'],"recover.php",true);
	$email = unesc(htmlspecialchars(trim($_POST["email"])));
	$email = safe_email($email);
	if (!$email)
	failedlogins($lang_recover['std_missing_email_address'],true);
	if (!check_email($email))
	failedlogins($lang_recover['std_invalid_email_address'],true);
	$res = sql_query("SELECT * FROM users WHERE email=" . sqlesc($email) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
	$arr = mysql_fetch_assoc($res);
	if (!$arr) failedlogins($lang_recover['std_email_not_in_database'],true);
	if ($arr['status'] == "pending") failedlogins($lang_recover['std_user_account_unconfirmed'],true);

	$sec = mksecret();

	sql_query("UPDATE users SET editsecret=" . sqlesc($sec) . " WHERE id=" . sqlesc($arr["id"])) or sqlerr(__FILE__, __LINE__);
	if (!mysql_affected_rows())
	stderr($lang_recover['std_error'], $lang_recover['std_database_error']);

	$hash = md5($sec . $email . $arr["passhash"] . $sec);
	$ip = getip() ;
	$title = $SITENAME.$lang_recover['mail_title'];
	$body = <<<EOD
{$lang_recover['mail_one']}($email){$lang_recover['mail_two']}$ip{$lang_recover['mail_three']}
<b><a href="javascript:void(null)" onclick="window.open('$baseUrl/recover.php?id={$arr["id"]}&secret=$hash')"> {$lang_recover['mail_this_link']} </a></b><br />
$baseUrl/recover.php?id={$arr["id"]}&secret=$hash
{$lang_recover['mail_four']}
EOD;

//	sent_mail($arr["email"],$SITENAME,$SITEEMAIL,change_email_encode(get_langfolder_cookie(), $title),change_email_encode(get_langfolder_cookie(),$body),"confirmation",true,false,'',get_email_encode(get_langfolder_cookie()));
	sent_mail($arr["email"],$SITENAME,$SITEEMAIL,$title,$body,"confirmation",true,false,'');

}
elseif($_SERVER["REQUEST_METHOD"] == "GET" && $take_recover && isset($_GET["id"]) && isset($_GET["secret"]))
{
	$id = intval($_GET["id"] ?? 0);
	$md5 = $_GET["secret"];
	if (!$id)
	httperr();

	$res = sql_query("SELECT username, email, passhash, editsecret FROM users WHERE id = " . sqlesc($id)) or sqlerr(__FILE__, __LINE__);
	$arr = mysql_fetch_array($res) or httperr();

	$email = $arr["email"];
	$sec = hash_pad($arr["editsecret"]);
	if (preg_match('/^ *$/s', $sec))
	httperr();
	if ($md5 != md5($sec . $email . $arr["passhash"] . $sec))
	httperr();

	// generate new password;
	$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

	$newpassword = "";
	for ($i = 0; $i < 10; $i++)
	$newpassword .= $chars[mt_rand(0, strlen($chars) - 1)];

	$sec = mksecret();

	$newpasshash = md5($sec . $newpassword . $sec);

	sql_query("UPDATE users SET secret=" . sqlesc($sec) . ", editsecret='', passhash=" . sqlesc($newpasshash) . " WHERE id=" . sqlesc($id)." AND editsecret=" . sqlesc($arr["editsecret"])) or sqlerr(__FILE__, __LINE__);

	if (!mysql_affected_rows())
	stderr($lang_recover['std_error'], $lang_recover['std_unable_updating_user_data']);
	$title = $SITENAME.$lang_recover['mail_two_title'];
	$body = <<<EOD
{$lang_recover['mail_two_one']}{$arr["username"]}
{$lang_recover['mail_two_two']}$newpassword
{$lang_recover['mail_two_three']}
<b><a href="javascript:void(null)" onclick="window.open('$baseUrl/login.php')">{$lang_recover['mail_here']}</a></b>
{$lang_recover['mail_three_1']}
<b><a href="http://www.google.com/support/bin/answer.py?answer=23852" target='_blank'>{$lang_confirm_resend['mail_google_answer']}</a></b>
{$lang_recover['mail_two_four']}

EOD;

	sent_mail($email,$SITENAME,$SITEEMAIL,$title,$body,"details",true,false,'');

}
else
{
	stdhead();
	$s = "<select name=\"sitelanguage\" onchange='submit()'>\n";

	$langs = langlist("site_lang");

	foreach ($langs as $row)
	{
		if ($row["site_lang_folder"] == get_langfolder_cookie()) $se = " selected=\"selected\""; else $se = "";
		$s .= "<option value=\"". $row["id"]."\"" . $se. ">" . htmlspecialchars($row["lang_name"]) . "</option>\n";
	}
	$s .= "\n</select>";
	?>
	<form method="get" action="<?php echo $_SERVER['PHP_SELF'] ?>">
	<?php
	print("<div align=\"right\">".$lang_recover['text_select_lang']. $s . "</div>");
	?>
	</form>
	<h1><?php echo $lang_recover['text_recover_user'] ?></h1>
	<p><?php echo $lang_recover['text_use_form_below'] ?></p>
 	<p><?php echo $lang_recover['text_reply_to_confirmation_email'] ?></p>
  	<p><b><?php echo $lang_recover['text_note'] ?><?php echo $maxloginattempts;?></b><?php echo $lang_recover['text_ban_ip'] ?></p>
	<p><?php echo $lang_recover['text_you_have'] ?><b><?php echo remaining ();?></b><?php echo $lang_recover['text_remaining_tries'] ?></p>
	<form method="post" action="recover.php">
	<table border="1" cellspacing="0" cellpadding="10">
	<tr><td class="rowhead"><?php echo $lang_recover['row_registered_email'] ?></td>
	<td class="rowfollow"><input type="text" style="width: 150px" name="email" /></td></tr>
	<?php
	show_image_code ();
	?>
	<tr><td class="toolbox" colspan="2" align="center"><input type="submit" value="<?php echo $lang_recover['submit_recover_it'] ?>" class="btn" /></td></tr>
	</table></form>
	<?php
	stdfoot();
}
