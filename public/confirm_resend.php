<?php
require "../include/bittorrent.php";
\Nexus\Database\NexusLock::lockOrFail("confirm_resend:lock:" . getip(), 10);
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
	check_code ($_POST['imagehash'] ?? null, $_POST['imagestring'] ?? null,"confirm_resend.php",true);
	$email = unesc(htmlspecialchars(trim($_POST["email"] ?? '')));
	$wantpassword = unesc(htmlspecialchars(trim($_POST["wantpassword"])));
	$passagain = unesc(htmlspecialchars(trim($_POST["passagain"])));

	$email = safe_email($email);
	if (empty($wantpassword) || empty($passagain) || empty($email))
		bark($lang_confirm_resend['std_fields_blank']);

	if (!check_email($email))
	failedlogins($lang_confirm_resend['std_invalid_email_address'],true);
	$arr = \Nexus\Database\NexusDB::table('users')
		->where('email', (string) $email)
		->limit(1)
		->first();
	$arr = $arr ? (array) $arr : null;
	if (!$arr) failedlogins($lang_confirm_resend['std_email_not_found'],true);
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

	$updated = \Nexus\Database\NexusDB::table('users')
		->where('id', (int) $arr["id"])
		->update([
			'passhash' => (string) $wantpasshash,
			'secret' => (string) $secret,
			'editsecret' => (string) $editsecret,
		]);

	if (!$updated)
	stderr($lang_confirm_resend['std_error'], $lang_confirm_resend['std_database_error']);

	$psecret = md5($editsecret);
	$ip = getip() ;
	$usern = $arr["username"];
	$id = $arr["id"];
	$title = $SITENAME.$lang_confirm_resend['mail_title'];
    $baseUrl = getSchemeAndHttpHost();
    $siteName = \App\Models\Setting::getSiteName();
    $mailTwo = sprintf($lang_confirm_resend['mail_two'], $siteName);
    $mailFive = sprintf($lang_confirm_resend['mail_five'], $siteName, $siteName, $REPORTMAIL, $siteName);
$body = <<<EOD
{$lang_confirm_resend['mail_one']}$usern{$mailTwo}($email){$lang_confirm_resend['mail_three']}$ip{$lang_confirm_resend['mail_four']}
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
	<?php echo sprintf($lang_confirm_resend['text_resend_confirmation_mail_note'], $maxloginattempts)?>
	<p><?php echo $lang_confirm_resend['text_you_have'] ?><b><?php echo remaining ();?></b><?php echo $lang_confirm_resend['text_remaining_tries'] ?></p>
    <?php $formInputStyle = 'style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box"'; ?>
    <form method="post" action="confirm_resend.php">
    <table border="1" cellspacing="0" cellpadding="10" style="width: min(100%, 420px);">
    <tr><td class="rowhead nowrap"><?php echo $lang_confirm_resend['row_registered_email'] ?></td>
    <td class="rowfollow"><input type="email" name="email" autocomplete="email" <?php echo $formInputStyle; ?> /></td></tr>
    <tr><td class="rowhead nowrap"><?php echo $lang_confirm_resend['row_new_password'] ?></td><td align="left"><input type="password" name="wantpassword" autocomplete="new-password" <?php echo $formInputStyle; ?> /><br />
        <font class="small"><?php echo $lang_confirm_resend['text_password_note'] ?></font></td></tr>
    <tr><td class="rowhead nowrap"><?php echo $lang_confirm_resend['row_enter_password_again'] ?></td><td align="left"><input type="password" name="passagain" autocomplete="new-password" <?php echo $formInputStyle; ?> /></td></tr>
	<?php
	show_image_code();
	?>
	<tr><td class="toolbox" colspan="2" align="center"><input type="submit" class="btn" value="<?php echo $lang_confirm_resend['submit_send_it'] ?>" /></td></tr>
	</table></form>
	<?php
	stdfoot();
}
