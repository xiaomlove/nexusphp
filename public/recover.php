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
$siteName = $SITENAME;
$mailTwoFour = sprintf($lang_recover['mail_two_four'], $siteName);
if ($_SERVER["REQUEST_METHOD"] == "POST")
{
	if ($iv == "yes")
	check_code ($_POST['imagehash'] ?? null, $_POST['imagestring'] ?? null,"recover.php",true);
	$email = unesc(htmlspecialchars(trim($_POST["email"] ?? '')));
	$email = safe_email($email);
	if (!$email)
	failedlogins($lang_recover['std_missing_email_address'],true);
	if (!check_email($email))
	failedlogins($lang_recover['std_invalid_email_address'],true);
	$arr = \Nexus\Database\NexusDB::table('users')
		->whereRaw('BINARY email = ?', [(string) $email])
		->limit(1)
		->first();
	$arr = $arr ? (array) $arr : null;
	if (!$arr) failedlogins($lang_recover['std_email_not_in_database'],true);
	if ($arr['status'] == "pending") failedlogins($lang_recover['std_user_account_unconfirmed'],true);

	$sec = mksecret();

	$updated = \Nexus\Database\NexusDB::table('users')
		->where('id', (int) $arr["id"])
		->update(['editsecret' => (string) $sec]);
	if (!$updated)
	stderr($lang_recover['std_error'], $lang_recover['std_database_error']);

	$hash = md5($sec . $email . $arr["passhash"] . $sec);
    do_log("hash: $hash = md5(sec: $sec . email: $email . passhash: {$arr['passhash']} . sec: $sec)");
	$ip = getip();
	$title = $SITENAME.$lang_recover['mail_title'];
    $mailOne = sprintf($lang_recover['mail_one'], $siteName);
    $mailFour = sprintf($lang_recover['mail_four'], $siteName);
    \Nexus\Database\NexusDB::cache_put("recover:$hash", now()->toDateTimeString());

	$body = <<<EOD
{$mailOne}($email){$lang_recover['mail_two']}$ip{$lang_recover['mail_three']}
<b><a href="$baseUrl/recover.php?id={$arr["id"]}&secret=$hash" target="_blank"> {$lang_recover['mail_this_link']} </a></b><br />
$baseUrl/recover.php?id={$arr["id"]}&secret=$hash
{$mailFour}
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
    if (!\Nexus\Database\NexusDB::cache_get("recover:$md5")) {
        do_log("secret: $md5 is expired", "error");
        httperr();
    }
	$arr = \Nexus\Database\NexusDB::table('users')
		->where('id', (int) $id)
		->select(['username', 'email', 'passhash', 'editsecret'])
		->first();
	$arr = $arr ? (array) $arr : null;
	if (!$arr) httperr();

	$email = $arr["email"];
	$sec = hash_pad($arr["editsecret"]);
	if ($md5 != md5($sec . $email . $arr["passhash"] . $sec)) {
        do_log("secret: $md5 != md5(sec: $sec . email: $email . passhash: {$arr['passhash']} . sec: $sec)","error");
        httperr();
    }

	// generate new password;
	$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

	$newpassword = "";
	for ($i = 0; $i < 10; $i++)
	$newpassword .= $chars[mt_rand(0, strlen($chars) - 1)];

	$sec = mksecret();

	$newpasshash = hash('sha256', $sec.hash('sha256', $newpassword));
    $authKey = mksecret();

	$updated = \Nexus\Database\NexusDB::table('users')
		->where('id', (int) $id)
		->where('editsecret', (string) $arr["editsecret"])
		->update([
			'secret' => (string) $sec,
			'editsecret' => '',
			'passhash' => (string) $newpasshash,
			'auth_key' => (string) $authKey,
		]);

	if (!$updated)
	stderr($lang_recover['std_error'], $lang_recover['std_unable_updating_user_data']);
	$title = $SITENAME.$lang_recover['mail_two_title'];
	$body = <<<EOD
{$lang_recover['mail_two_one']}{$arr["username"]}
{$lang_recover['mail_two_two']}$newpassword
{$lang_recover['mail_two_three']}
<b><a href="$baseUrl/login.php">{$lang_recover['mail_here']}</a></b>
{$mailTwoFour}
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
<?php $formInputStyle = 'style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box"'; ?>
<td class="rowfollow"><input type="email" <?php echo $formInputStyle; ?> name="email" autocomplete="email" /></td></tr>
	<?php
	show_image_code ();
	?>
	<tr><td class="toolbox" colspan="2" align="center"><input type="submit" value="<?php echo $lang_recover['submit_recover_it'] ?>" class="btn" /></td></tr>
	</table></form>
	<?php
	stdfoot();
}
