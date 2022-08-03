<?php
require "../include/bittorrent.php";
dbconn();
failedloginscheck ("Re-send",true);

$langid = intval($_GET['sitelanguage'] ?? 0);
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
	global $lang_confirm_resend;
	stdhead();
	stdmsg($lang_confirm_resend['resend_confirmation_email_failed'], $msg);
	stdfoot();
	exit;
}
if ($verification == "admin")
bark($lang_confirm_resend['std_need_admin_verification']);

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
	if ($iv == "yes")
	check_code ($_POST['imagehash'], $_POST['imagestring'],"confirm_resend.php",true);
	$email = unesc(htmlspecialchars(trim($_POST["email"])));
	$wantpassword = unesc(htmlspecialchars(trim($_POST["wantpassword"])));
	$passagain = unesc(htmlspecialchars(trim($_POST["passagain"])));

	$email = safe_email($email);
	if (empty($wantpassword) || empty($passagain) || empty($email))
		bark($lang_confirm_resend['std_fields_blank']);

	if (!check_email($email))
	failedlogins($lang_confirm_resend['std_invalid_email_address'],true);
	$res = sql_query("SELECT * FROM users WHERE email=" . sqlesc($email) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
	$arr = mysql_fetch_assoc($res) or failedlogins($lang_confirm_resend['std_email_not_found'],true);
	if($arr["status"] != "pending") failedlogins($lang_confirm_resend['std_user_already_confirm'],true);

	if ($wantpassword != $passagain)
		bark($lang_confirm_resend['std_passwords_unmatched']);

	if (strlen($wantpassword) < 6)
		bark($lang_confirm_resend['std_password_too_short']);

	if (strlen($wantpassword) > 40)
		bark($lang_confirm_resend['std_password_too_long']);

	if ($wantpassword == $wantusername)
		bark($lang_confirm_resend['std_password_equals_username']);

	$secret = mksecret();
	$wantpasshash = md5($secret . $wantpassword . $secret);
	$editsecret = ($verification == 'admin' ? '' : $secret);

	sql_query("UPDATE users SET passhash=" .sqlesc($wantpasshash) . ",secret=" . sqlesc($secret) . ",editsecret=" . sqlesc($editsecret) . " WHERE id=" . sqlesc($arr["id"])) or sqlerr(__FILE__, __LINE__);

	if (!mysql_affected_rows())
	stderr($lang_confirm_resend['std_error'], $lang_confirm_resend['std_database_error']);

	$psecret = md5($editsecret);
	$ip = getip() ;
	$usern = $arr["username"];
	$id = $arr["id"];
	$title = $SITENAME.$lang_confirm_resend['mail_title'];
    $baseUrl = getSchemeAndHttpHost();
$body = <<<EOD
{$lang_confirm_resend['mail_one']}$usern{$lang_confirm_resend['mail_two']}($email){$lang_confirm_resend['mail_three']}$ip{$lang_confirm_resend['mail_four']}
<b><a href="javascript:void(null)" onclick="window.open('{$baseUrl}/confirm.php?id=$id&secret=$psecret')">
{$lang_confirm_resend['mail_this_link']} </a></b><br />
{$baseUrl}/confirm.php?id=$id&secret=$psecret
{$lang_confirm_resend['mail_four_1']}
<b><a href="javascript:void(null)" onclick="window.open('{$baseUrl}/confirm_resend.php')">{$lang_confirm_resend['mail_here']}</a></b><br />
{$baseUrl}/confirm_resend.php
<br />
{$lang_confirm_resend['mail_five']}
EOD;

	sent_mail($email,$SITENAME,$SITEEMAIL,$title,$body,"signup",false,false,'');
	header("Location: " . "{$baseUrl}/ok.php?type=signup&email=" . rawurlencode($email));
}
else
{
	stdhead();
	$s = "<select name=\"sitelanguage\" onchange='submit()'>\n";

	$langs = langlist("site_lang");

	foreach ($langs as $row)
	{
		if ($row["site_lang_folder"] == get_langfolder_cookie()) $se = " selected=\"selected\""; else $se = "";
		$s .= "<option value=\"". $row["id"]."\" " . $se. ">" . htmlspecialchars($row["lang_name"]) . "</option>\n";
	}
	$s .= "\n</select>";
	?>
	<form method="get" action="<?php echo $_SERVER['PHP_SELF'] ?>">
<?php
	print("<div align=\"right\">".$lang_confirm_resend['text_select_lang']. $s . "</div>");
?>
	</form>
	<?php echo $lang_confirm_resend['text_resend_confirmation_mail_note']?>
	<p><?php echo $lang_confirm_resend['text_you_have'] ?><b><?php echo remaining ();?></b><?php echo $lang_confirm_resend['text_remaining_tries'] ?></p>
	<form method="post" action="confirm_resend.php">
	<table border="1" cellspacing="0" cellpadding="10">
	<tr><td class="rowhead nowrap"><?php echo $lang_confirm_resend['row_registered_email'] ?></td>
	<td class="rowfollow"><input type="text" style="width: 200px" name="email" /></td></tr>
	<tr><td class="rowhead nowrap"><?php echo $lang_confirm_resend['row_new_password'] ?></td><td align="left"><input type="password" style="width: 200px" name="wantpassword" /><br />
		<font class="small"><?php echo $lang_confirm_resend['text_password_note'] ?></font></td></tr>
	<tr><td class="rowhead nowrap"><?php echo $lang_confirm_resend['row_enter_password_again'] ?></td><td align="left"><input type="password" style="width: 200px" name="passagain" /></td></tr>
	<?php
	show_image_code();
	?>
	<tr><td class="toolbox" colspan="2" align="center"><input type="submit" class="btn" value="<?php echo $lang_confirm_resend['submit_send_it'] ?>" /></td></tr>
	</table></form>
	<?php
	stdfoot();
}
