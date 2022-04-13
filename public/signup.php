<?php
require_once("../include/bittorrent.php");
dbconn();

$langid = intval($_GET['sitelanguage'] ?? 0);
if ($langid)
{
	$lang_folder = validlang($langid);
    $enabled = \App\Models\Language::listEnabled();
    if (!in_array($lang_folder, $enabled)) {
        nexus_redirect(getBaseUrl());
    }
	if(get_langfolder_cookie() != $lang_folder)
	{
		set_langfolder_cookie($lang_folder);
        nexus_redirect($_SERVER['REQUEST_URI']);
	}
}
require_once(get_langfile_path("", false, $CURLANGDIR));
cur_user_check ();
$type = $_GET['type'] ?? '';
if ($type == 'invite')
{
	registration_check();
	failedloginscheck ("Invite signup");
	$code = $_GET["invitenumber"];

	$nuIP = getip();
	$dom = @gethostbyaddr($nuIP);
	if ($dom == $nuIP || @gethostbyname($dom) != $nuIP)
	$dom = "";
	else
	{
	$dom = strtoupper($dom);
	preg_match('/^(.+)\.([A-Z]{2,3})$/', $dom, $tldm);
	$dom = $tldm[2];
	}

	$sq = sprintf("SELECT inviter FROM invites WHERE valid = %s and hash ='%s'", \App\Models\Invite::VALID_YES, mysql_real_escape_string($code));
	$res = sql_query($sq) or sqlerr(__FILE__, __LINE__);
	$inv = mysql_fetch_assoc($res);
	$inviter = htmlspecialchars($inv["inviter"]);
	if (!$inv)
		stderr($lang_signup['std_error'], $lang_signup['std_uninvited'], 0);
	stdhead($lang_signup['head_invite_signup']);
}
else {
	registration_check("normal");
	failedloginscheck ("Signup");
	stdhead($lang_signup['head_signup']);
}

$s = "<select name=\"sitelanguage\" onchange='submit()'>\n";

$langs = langlist("site_lang", true);

foreach ($langs as $row)
{
	if ($row["site_lang_folder"] == get_langfolder_cookie()) $se = " selected"; else $se = "";
	$s .= "<option value=". $row["id"] . $se. ">" . htmlspecialchars($row["lang_name"]) . "</option>\n";
}
$s .= "\n</select>";
?>
<form method="get" action=<?php echo $_SERVER['PHP_SELF'] ?>>
<?php
if ($type == 'invite')
print("<input type=hidden name=type value='invite'><input type=hidden name=invitenumber value='".$code."'>");
print("<div align=right valign=top>".$lang_signup['text_select_lang']. $s . "</div>");
?>
</form>
<p>
<form method="post" action="takesignup.php">
<?php if ($type == 'invite') print("<input type=\"hidden\" name=\"inviter\" value=\"".$inviter."\"><input type=hidden name=type value='invite'");?>
<table border="1" cellspacing="0" cellpadding="10">
<?php
print("<tr><td class=text align=center colspan=2>".$lang_signup['text_cookies_note']."</td></tr>");
?>
<tr><td class=rowhead><?php echo $lang_signup['row_desired_username'] ?></td><td class=rowfollow align=left><input type="text" style="width: 200px" name="wantusername" /><br />
<font class=small><?php echo $lang_signup['text_allowed_characters'] ?></font></td></tr>
<tr><td class=rowhead><?php echo $lang_signup['row_pick_a_password'] ?></td><td class=rowfollow align=left><input type="password" style="width: 200px" name="wantpassword" /><br />
	<font class=small><?php echo $lang_signup['text_minimum_six_characters'] ?></font></td></tr>
<tr><td class=rowhead><?php echo $lang_signup['row_enter_password_again'] ?></td><td class=rowfollow align=left><input type="password" style="width: 200px" name="passagain" /></td></tr>
<?php
show_image_code ();
?>
<tr><td class=rowhead><?php echo $lang_signup['row_email_address'] ?></td><td class=rowfollow align=left><input type="text" style="width: 200px" name="email" />
<table width=250 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded><font class=small><?php echo ($restrictemaildomain == 'yes' ? $lang_signup['text_email_note'].allowedemails() : "") ?></td></tr>
</font></td></tr></table>
</td></tr>
<?php $countries = "<option value=\"8\">---- ".$lang_signup['select_none_selected']." ----</option>n";
$ct_r = sql_query("SELECT id,name FROM countries ORDER BY name") or die;
while ($ct_a = mysql_fetch_array($ct_r))
$countries .= "<option value=$ct_a[id]" . ($ct_a['id'] == 8 ? " selected" : "") . ">$ct_a[name]</option>n";
tr($lang_signup['row_country'], "<select name=country>n$countries</select>", 1);
//School select
if ($showschool == 'yes'){
$schools = "<option value=35>---- ".$lang_signup['select_none_selected']." ----</option>n";
$sc_r = sql_query("SELECT id,name FROM schools ORDER BY name") or die;
while ($sc_a = mysql_fetch_array($sc_r))
$schools .= "<option value=$sc_a[id]" . ($sc_a['id'] == 35 ? " selected" : "") . ">$sc_a[name]</option>n";
tr($lang_signup['row_school'], "<select name=school>$schools</select>", 1);
}
?>
<tr><td class=rowhead><?php echo $lang_signup['row_gender'] ?></td><td class=rowfollow align=left>
<input type=radio name=gender value=Male><?php echo $lang_signup['radio_male'] ?><input type=radio name=gender value=Female><?php echo $lang_signup['radio_female'] ?></td></tr>
<tr><td class=rowhead><?php echo $lang_signup['row_verification'] ?></td><td class=rowfollow align=left><input type=checkbox name=rulesverify value=yes><?php echo $lang_signup['checkbox_read_rules'] ?><br />
<input type=checkbox name=faqverify value=yes><?php echo $lang_signup['checkbox_read_faq'] ?> <br />
<input type=checkbox name=ageverify value=yes><?php echo $lang_signup['checkbox_age'] ?></td></tr>
<input type=hidden name=hash value=<?php echo $code?>>
<tr><td class=toolbox colspan="2" align="center"><font color=red><b><?php echo $lang_signup['text_all_fields_required'] ?></b><p></font><input type=submit value=<?php echo $lang_signup['submit_sign_up'] ?> style='height: 25px'></td></tr>
</table>
</form>
<?php
stdfoot();
