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

failedloginscheck ();
cur_user_check () ;
stdhead($lang_login['head_login']);

$s = "<select name=\"sitelanguage\" onchange='submit()'>\n";

$langs = langlist("site_lang", true);
foreach ($langs as $row)
{
	if ($row["site_lang_folder"] == get_langfolder_cookie()) $se = "selected=\"selected\""; else $se = "";
	$s .= "<option value=\"". $row["id"] ."\" ". $se. ">" . htmlspecialchars($row["lang_name"]) . "</option>\n";
}
$s .= "\n</select>";
?>
<form method="get" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
    <input type="hidden" name="secret" value="<?php echo $_GET['secret'] ?? '' ?>">
<?php
print("<div align=\"right\">".$lang_login['text_select_lang']. $s . "</div>");
?>
</form>
<?php

unset($returnto);
if (!empty($_GET["returnto"])) {
	$returnto = $_GET["returnto"];
	if (empty($_GET["nowarn"])) {
		print("<h1>" . $lang_login['h1_not_logged_in']. "</h1>\n");
		print("<p><b>" . $lang_login['p_error']. "</b> " . $lang_login['p_after_logged_in']. "</p>\n");
	}
}
?>
<form method="post" action="takelogin.php">
    <input type="hidden" name="secret" value="<?php echo $_GET['secret'] ?? ''?>">
<p><?php echo $lang_login['p_need_cookies_enables']?><br /> [<b><?php echo $maxloginattempts;?></b>] <?php echo $lang_login['p_fail_ban']?></p>
<p><?php echo $lang_login['p_you_have']?> <b><?php echo remaining ();?></b> <?php echo $lang_login['p_remaining_tries']?></p>
<table border="0" cellpadding="5">
<tr><td class="rowhead"><?php echo $lang_login['rowhead_username']?></td><td class="rowfollow" align="left"><input type="text" name="username" style="width: 180px; border: 1px solid gray" /></td></tr>
<tr><td class="rowhead"><?php echo $lang_login['rowhead_password']?></td><td class="rowfollow" align="left"><input type="password" name="password" style="width: 180px; border: 1px solid gray"/></td></tr>
<tr><td class="rowhead"><?php echo $lang_login['rowhead_two_step_code']?></td><td class="rowfollow" align="left"><input type="text" name="two_step_code"  placeholder="<?php echo $lang_login['two_step_code_tooltip'] ?>" style="width: 180px; border: 1px solid gray"/></td></tr>
<?php
show_image_code ();
if ($securelogin == "yes")
	$sec = "checked=\"checked\" disabled=\"disabled\"";
elseif ($securelogin == "no")
	$sec = "disabled=\"disabled\"";
elseif ($securelogin == "op")
	$sec = "";

if ($securetracker == "yes")
	$sectra = "checked=\"checked\" disabled=\"disabled\"";
elseif ($securetracker == "no")
	$sectra = "disabled=\"disabled\"";
elseif ($securetracker == "op")
	$sectra = "";
?>
<tr><td class="toolbox" colspan="2" align="left"><?php echo $lang_login['text_advanced_options']?></td></tr>
<tr><td class="rowhead"><?php echo $lang_login['text_auto_logout']?></td><td class="rowfollow" align="left"><input class="checkbox" type="checkbox" name="logout" value="yes" /><?php echo $lang_login['checkbox_auto_logout']?></td></tr>
<tr><td class="rowhead"><?php echo $lang_login['text_restrict_ip']?></td><td class="rowfollow" align="left"><input class="checkbox" type="checkbox" name="securelogin" value="yes" /><?php echo $lang_login['checkbox_restrict_ip']?></td></tr>
<tr><td class="rowhead"><?php echo $lang_login['text_ssl']?></td><td class="rowfollow" align="left"><input class="checkbox" type="checkbox" name="ssl" value="yes" <?php echo $sec?> /><?php echo $lang_login['checkbox_ssl']?><br /><input class="checkbox" type="checkbox" name="trackerssl" value="yes" <?php echo $sectra?> /><?php echo $lang_login['checkbox_ssl_tracker']?></td></tr>
<tr><td class="toolbox" colspan="2" align="right"><input type="submit" value="<?php echo $lang_login['button_login']?>" class="btn" /> <input type="reset" value="<?php echo $lang_login['button_reset']?>" class="btn" /></td></tr>
</table>
<?php

if (isset($returnto))
	print("<input type=\"hidden\" name=\"returnto\" value=\"" . htmlspecialchars($returnto) . "\" />\n");

?>
</form>
<p>[<b><a href="complains.php"><?= $lang_login['text_complain'] ?></a></b>]</p>
<p><?php echo $lang_login['p_no_account_signup']?></p>
<?php
if ($smtptype != 'none'){
?>
<p><?php echo $lang_login['p_forget_pass_recover']?></p>
<p><?php echo $lang_login['p_account_banned']?></p>
<p><?php echo $lang_login['p_resend_confirm']?></p>
<?php
}
if ($showhelpbox_main != 'no'){?>
<table width="100%" class="main" border="0" cellspacing="0" cellpadding="0"><tr><td class="embedded">
<h2><?php echo $lang_login['text_helpbox'] ?><font class="small"> - <?php echo $lang_login['text_helpbox_note'] ?><font id= "waittime" color="red"></font></h2>
<?php
print("<table width='100%' border='1' cellspacing='0' cellpadding='1'><tr><td class=\"text\">\n");
print("<iframe src='" . get_protocol_prefix() . $BASEURL . "/shoutbox.php?type=helpbox' width='100%' height='180' frameborder='0' name='sbox' marginwidth='0' marginheight='0'></iframe><br /><br />\n");
print("<form action='" . get_protocol_prefix() . $BASEURL . "/shoutbox.php' id='helpbox' method='get' target='sbox' name='shbox'>\n");
print("<div style='display: flex'>" . $lang_login['text_message']."<input type='text' id=\"hbtext\" name='shbox_text' autocomplete='off' style='flex-grow: 1;width: 500px; border: 1px solid gray' ><input type='submit' id='hbsubmit' class='btn' name='shout' value=\"".$lang_login['sumbit_shout']."\" /><input type='reset' class='btn' value=".$lang_login['submit_clear']." /> <input type='hidden' name='sent' value='yes'><input type='hidden' name='type' value='helpbox' /></div>\n");
print("<div id=sbword style=\"display: none\">".$lang_login['sumbit_shout']."</div>");
print(smile_row("shbox","shbox_text"));
print("</td></tr></table></form></td></tr></table>");
}
stdfoot();
