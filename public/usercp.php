<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();

function bark($msg) {
	//stdhead();
	global $lang_usercp;
	stdmsg($lang_usercp['std_sorry'], $msg);
	//stdfoot();
	exit;
}
function usercpmenu ($selected = "home") {
	global $lang_usercp;
	begin_main_frame();
	print ("<div id=\"usercpnav\"><ul id=\"usercpmenu\" class=\"menu\">");
	print ("<li" . ($selected == "home" ? " class=selected" : "") . "><a href=\"usercp.php\">".$lang_usercp['text_user_cp_home']."</a></li>");
	print ("<li" . ($selected == "personal" ? " class=selected" : "") . "><a href=\"?action=personal\">".$lang_usercp['text_personal_settings']."</a></li>");
	print ("<li" . ($selected == "tracker" ? " class=selected" : "") . "><a href=\"?action=tracker\">".$lang_usercp['text_tracker_settings']."</a></li>");
	print ("<li" . ($selected == "forum" ? " class=selected" : "") . "><a href=\"?action=forum\">".$lang_usercp['text_forum_settings']."</a></li>");
	print ("<li" . ($selected == "security" ? " class=selected" : "") . "><a href=\"?action=security\">".$lang_usercp['text_security_settings']."</a></li>");
	print ("</ul></div>");
	end_main_frame();
}
function getimagewidth ($imagewidth, $imageheight)
{
	while (($imagewidth > 150) or ($imageheight > 150))
	{
		$imagewidth=150;
		$imageheight=150;
	}
	return $imagewidth;
}
function getimageheight ($imagewidth, $imageheight)
{
	while (($imagewidth > 150) or ($imageheight > 150))
	{
		$imagewidth=150;
		$imageheight=150;
	}
	return $imageheight;
}
function form($name) {
	return print("<form method=post action=usercp.php><input type=hidden name=action value=".htmlspecialchars($name)."><input type=hidden name=type value=save>");
}
function submit() {
	global $lang_usercp;
	print("<tr><td class=\"rowhead\" valign=\"top\" align=\"right\">".$lang_usercp['row_save_settings']."</td><td class=\"rowfollow\" valign=\"top\" align=left><input type=submit value=\"".$lang_usercp['submit_save_settings']."\"></td></tr>"."</form>");
}
function format_tz($a)
{
	$h = floor($a);
	$m = ($a - floor($a)) * 60;
	return ($a >= 0?"+":"-") . (strlen(abs($h)) > 1?"":"0") . abs($h) .
	":" . ($m==0?"00":$m);
}
function priv($name, $descr) {
	global $CURUSER;
	if ($CURUSER["privacy"] == $name)
	return "<input type=\"radio\" name=\"privacy\" value=\"".htmlspecialchars($name)."\" checked=\"checked\" /> ".htmlspecialchars($descr);
	else
	return "<input type=\"radio\" name=\"privacy\" value=\"".htmlspecialchars($name)."\" /> ".htmlspecialchars($descr);
}
function goback ($where = "-1") {
	global $lang_usercp;
	$text = $lang_usercp['text_go_back'];
	$goback = "<a class=faqlink HREF=\"javascript:history.go(".htmlspecialchars($where).")\">".htmlspecialchars($text)."</a>";
	return $goback;
}
$action = isset($_POST['action']) ? htmlspecialchars($_POST['action']) : (isset($_GET['action']) ? htmlspecialchars($_GET['action']) : '');
$type = isset($_POST['type']) ? htmlspecialchars($_POST['type']) : (isset($_GET['type']) ? htmlspecialchars($_GET['type']) : '');

$allowed_actions = array("personal","tracker","forum","security");
if ($action){
	if (!in_array($action, $allowed_actions))
		stderr($lang_usercp['std_error'], $lang_usercp['std_invalid_action']);
	else {
	switch ($action) {
		case "personal":
			if ($type == 'save') {
				$updateset = array();
				$parked = $_POST["parked"];
				if ($parked != 'yes')
					$parked = 'no';
				$acceptpms = $_POST["acceptpms"];
				$deletepms = ($_POST["deletepms"] != "" ? "yes" : "no");
				$savepms = ($_POST["savepms"] != "" ? "yes" : "no");
				$commentpm = $_POST["commentpm"];
				$gender = $_POST["gender"];
				$country = $_POST["country"];
				if ($showschool = 'yes'){
					$school = $_POST["school"];
					$updateset[] = "school = ".sqlesc($school);
					}
				$download = $_POST["download"];
				$upload = $_POST["upload"];
				$isp = $_POST["isp"];
				//	$tzoffset = $_POST["tzoffset"];
				if ( $_POST["avatar"] == '' )
				$avatar=$_POST["savatar"];
				else
				$avatar = $_POST["avatar"];

				if(preg_match("/^https?:\/\/[^\s'\"<>]+\.(jpg|gif|png|jpeg)$/i", $avatar) && !preg_match("/\.php/i",$avatar) && !preg_match("/\.js/i",$avatar) && !preg_match("/\.cgi/i",$avatar)) {
					$avatar = htmlspecialchars( trim( $avatar ) );
					$updateset[] = "avatar = " . sqlesc($avatar);
				}
				$info = htmlspecialchars(trim($_POST["info"]));

				$updateset[] = "parked = " . sqlesc($parked);
				$updateset[] = "acceptpms = " . sqlesc($acceptpms);
				$updateset[] = "deletepms = " . sqlesc($deletepms);
				$updateset[] = "savepms = " . sqlesc($savepms);
				$updateset[] = "commentpm = " . sqlesc($commentpm);
				$updateset[] = "gender = " . sqlesc($gender);
				if (is_valid_id($country))
				$updateset[] = "country = " . sqlesc($country);
				if (is_valid_id($download))
				$updateset[] = "download =  " . sqlesc($download);
				if (is_valid_id($upload))
				$updateset[] = "upload =  " . sqlesc($upload);
				if (is_valid_id($isp))
				$updateset[] = "isp =  " . sqlesc($isp);
				//	$updateset[] = "tzoffset = " . sqlesc($tzoffset);

				$updateset[] = "info = " . sqlesc($info);

				$query = "UPDATE users SET " . implode(",", $updateset) . " WHERE id = ".sqlesc($CURUSER["id"]);
				$result = sql_query($query);
				if (!$result)
				sqlerr(__FILE__,__LINE__);
				else
				header("Location: usercp.php?action=personal&type=saved");
			}
			stdhead($lang_usercp['head_control_panel'].$lang_usercp['head_personal_settings'],true);

			$countries = "<option value=0>---- ".$lang_usercp['select_none_selected']." ----</option>\n";
			$ct_r = sql_query("SELECT id,name FROM countries ORDER BY name") or die;
			while ($ct_a = mysql_fetch_array($ct_r))
			$countries .= "<option value=".htmlspecialchars($ct_a['id'])."" . (htmlspecialchars($CURUSER["country"]) == htmlspecialchars($ct_a['id']) ? " selected" : "") . ">".htmlspecialchars($ct_a['name'])."</option>\n";
			$isplist = "<option value=0>---- ".$lang_usercp['select_none_selected']." ----</option>\n";
			$isp_r = sql_query("SELECT id,name FROM isp ORDER BY id ASC") or die;
			while ($isp_a = mysql_fetch_array($isp_r))
			$isplist .= "<option value=".htmlspecialchars($isp_a['id'])."" . (htmlspecialchars($CURUSER["isp"]) == htmlspecialchars($isp_a['id']) ? " selected" : "") . ">".htmlspecialchars($isp_a['name'])."</option>\n";
			$downloadspeed = "<option value=0>---- ".$lang_usercp['select_none_selected']." ----</option>\n";
			$ds_a = sql_query("SELECT id,name FROM downloadspeed ORDER BY id") or die;
			while ($ds_b = mysql_fetch_array($ds_a))
			$downloadspeed .= "<option value=".htmlspecialchars($ds_b['id'])."" . (htmlspecialchars($CURUSER["download"]) == htmlspecialchars($ds_b['id']) ? " selected" : "") . ">".htmlspecialchars($ds_b['name'])."</option>\n";

			$uploadspeed = "<option value=0>---- ".$lang_usercp['select_none_selected']." ----</option>\n";
			$us_a = sql_query("SELECT id,name FROM uploadspeed ORDER BY id") or die;
			while ($us_b = mysql_fetch_array($us_a))
			$uploadspeed .= "<option value=".htmlspecialchars($us_b['id'])."" . (htmlspecialchars($CURUSER["upload"]) == htmlspecialchars($us_b['id']) ? " selected" : "") . ">".htmlspecialchars($us_b['name'])."</option>\n";
			$ra=sql_query("SELECT * FROM bitbucket WHERE public = '1'");
			$options='';
			$text = '';
			while ($sor=mysql_fetch_array($ra))
			{
				$text.='<option value="'. get_protocol_prefix() . $BASEURL .'/bitbucket/'.$sor["name"].'">'.$sor["name"].'</option>';
			}

			usercpmenu ("personal");
			print ("<table border=0 cellspacing=0 cellpadding=5 width=1200>");
			if ($type == 'saved')
				print("<tr><td colspan=2 class=\"heading\" valign=\"top\" align=\"center\"><font color=red>".$lang_usercp['text_saved']."</font></td></tr>\n");

			form ("personal");
			tr_small($lang_usercp['row_account_parked'],
			"<input type=checkbox name=parked" . ($CURUSER["parked"] == "yes" ? " checked" : "") . " value=yes>".$lang_usercp['checkbox_pack_my_account']."<br /><font class=small size=1>".$lang_usercp['text_account_pack_note']."</font>"
			,1);
			tr_small($lang_usercp['row_pms'],$lang_usercp['text_accept_pms']."<input type=radio name=acceptpms" . ($CURUSER["acceptpms"] == "yes" ? " checked" : "") . " value=yes>".$lang_usercp['radio_all_except_blocks']."<input type=radio name=acceptpms" .  ($CURUSER["acceptpms"] == "friends" ? " checked" : "") . " value=friends>".$lang_usercp['radio_friends_only']."<input type=radio name=acceptpms" .  ($CURUSER["acceptpms"] == "no" ? " checked" : "") . " value=no>".$lang_usercp['radio_staff_only']."<br /><input type=checkbox name=deletepms" . ($CURUSER["deletepms"] == "yes" ? " checked" : "") . "> ".$lang_usercp['checkbox_delete_pms']."<br /><input type=checkbox name=savepms" . ($CURUSER["savepms"] == "yes" ? " checked" : "") . "> ".$lang_usercp['checkbox_save_pms']."<br /><input type=checkbox name=commentpm" . ($CURUSER["commentpm"] == "yes" ? " checked" : "") . " value=yes> ".$lang_usercp['checkbox_pm_on_comments'],1);

			tr_small($lang_usercp['row_gender'],
			"<input type=radio name=gender" . ($CURUSER["gender"] == "N/A" ? " checked" : "") . " value=N/A>".$lang_usercp['radio_not_available']."
<input type=radio name=gender" . ($CURUSER["gender"] == "Male" ? " checked" : "") . " value=Male>".$lang_usercp['radio_male']."<input type=radio name=gender" .  ($CURUSER["gender"] == "Female" ? " checked" : "") . " value=Female>".$lang_usercp['radio_female'],1);
			tr_small($lang_usercp['row_country'], "<select name=country>\n$countries\n</select>",1);
		//School select
if ($showschool == 'yes'){
$schools = "<option value=35>---- ".$lang_usercp['select_none_selected']." ----</option>n";
$sc_r = sql_query("SELECT id,name FROM schools ORDER BY name") or die;
while ($sc_a = mysql_fetch_array($sc_r))
$schools .= "<option value={$sc_a['id']}" . ($sc_a['id'] == $CURUSER['school'] ? " selected" : "") . ">{$sc_a['name']}</option>n";
tr($lang_usercp['row_school'], "<select name=school>$schools</select>", 1);
}
			tr_small($lang_usercp['row_network_bandwidth'], "<b>".$lang_usercp['text_downstream_rate']. "</b>: <select name=download>".$downloadspeed."</select>&nbsp;&nbsp;<b>".$lang_usercp['text_upstream_rate']."</b>: <select name=upload>".$uploadspeed."</select>&nbsp;&nbsp;<b>".$lang_usercp['text_isp']."</b>: <select name=isp>".$isplist."</select>",1);
			tr_small($lang_usercp['row_avatar_url'], "<img src=".($CURUSER["avatar"] ? "'$CURUSER[avatar]'" : "'" . get_protocol_prefix() . $BASEURL . "/pic/default_avatar.png'")." name='avatarimg'><br />
  <select name=savatar OnChange=\"document.forms[0].avatarimg.src=this.value;this.form.avatar.value=this.value;\">
  <option value='$CURUSER[avatar]'>".$lang_usercp['select_choose_avatar']."</option>
  <option value='" . get_protocol_prefix() . $BASEURL . "/pic/default_avatar.png'>".$lang_usercp['select_nothing']."</option>
  $text
  </select><input type=text name=avatar style=\"width: 400px\" value=\"" . htmlspecialchars($CURUSER["avatar"]) .
  "\"><br />\n".$lang_usercp['text_avatar_note'].($enablebitbucket_main == 'yes' ? $lang_usercp['text_bitbucket_note'] : ""),1);
  tr($lang_usercp['row_info'], "<textarea name=\"info\" style=\"width:700px\" rows=\"10\" >" . htmlspecialchars($CURUSER["info"]) . "</textarea><br />".$lang_usercp['text_info_note'], 1);
  submit();
  print("</table>");
  stdfoot();
  die;
  break;
		case "tracker":
			$showaddisabled = true;
			if ($enablead_advertisement == 'yes'){
				if (get_user_class() >= $noad_advertisement || ($enablebonusnoad_advertisement == 'yes' && strtotime($CURUSER['noaduntil']) >= TIMENOW)){
					$showaddisabled = false;
				}
			}
			if ($enabletooltip_tweak == 'yes')
				$showtooltipsetting = true;
			else
				$showtooltipsetting = false;
			if ($type == 'save') {
				$updateset = array();
				$pmnotif = $_POST["pmnotif"] ?? '';
				$emailnotif = $_POST["emailnotif"] ?? '';
				$notifs = ($pmnotif == 'yes' ? "[pm]" : "");
				$notifs .= ($emailnotif == 'yes' ? "[email]" : "");

			function browsecheck($dbtable = "categories", $cbname = "cat"){
				global $_POST;
				$return = "";
				$r = sql_query("SELECT id FROM ".$dbtable) or sqlerr();
				$rows = mysql_num_rows($r);
				for ($i = 0; $i < $rows; ++$i)
					{
						$a = mysql_fetch_assoc($r);
						if (isset($_POST[$cbname.$a['id']]) && $_POST[$cbname.$a['id']] == 'yes')
						$return .= "[".$cbname.$a['id']."]";
					}
				return $return;
				}
				/*$r = sql_query("SELECT id FROM categories") or sqlerr();
				$rows = mysql_num_rows($r);
				for ($i = 0; $i < $rows; ++$i)
				{
					$a = mysql_fetch_assoc($r);
					if ($_POST["cat$a['id']"] == 'yes')
					$notifs .= "[cat$a['id']]";
				}*/
				$notifs .= browsecheck("categories", "cat");
				$notifs .= browsecheck("sources", "sou");
				$notifs .= browsecheck("media", "med");
				$notifs .= browsecheck("codecs", "cod");
				$notifs .= browsecheck("standards", "sta");
				$notifs .= browsecheck("processings", "pro");
				$notifs .= browsecheck("teams", "tea");
				$notifs .= browsecheck("audiocodecs", "aud");
				$incldead = $_POST["incldead"];
				if (isset($incldead) && $incldead != 1)
					$notifs .= "[incldead=".$incldead."]";
				$spstate = $_POST["spstate"];
				if ($spstate)
					$notifs .= "[spstate=".$spstate."]";
				$inclbookmarked = $_POST["inclbookmarked"];
				if ($inclbookmarked)
					$notifs .= "[inclbookmarked=".$inclbookmarked."]";
				$stylesheet = $_POST["stylesheet"];
//				$caticon = $_POST["caticon"];
				$sitelanguage = $_POST["sitelanguage"];
				$fontsize = $_POST["fontsize"];
				if ($fontsize == 'large')
					$updateset[] = "fontsize = 'large'";
				elseif ($fontsize == 'small')
					$updateset[] = "fontsize = 'small'";
				else $updateset[] = "fontsize = 'medium'";
				$updateset[] = "notifs = " . sqlesc($notifs);

				if (is_valid_id($stylesheet))
				$updateset[] = "stylesheet = " . sqlesc($stylesheet);
//				if (is_valid_id($caticon))
//				$updateset[] = "caticon = " . sqlesc($caticon);

				if (is_valid_id($sitelanguage))
				{
					$lang_folder = validlang($sitelanguage);
					if(get_langfolder_cookie() != $lang_folder)
					{
						set_langfolder_cookie($lang_folder);
						header("Location: " . $_SERVER['PHP_SELF']);
					}
					$updateset[] = "lang = " . sqlesc($sitelanguage);
				}

				$updateset[] = "torrentsperpage = " . min(100, intval($_POST["torrentsperpage"] ?? 0));
				if ($showmovies['hot'] == "yes"){
					$showhot = $_POST["show_hot"];
					$updateset[] = "showhot = " . sqlesc($showhot);
					}
				if ($showmovies['classic'] == "yes"){
					$showclassic = $_POST["show_classic"];
					$updateset[] = "showclassic = " . sqlesc($showclassic);
					}
				if ($showtooltipsetting){
					$tooltip = $_POST['tooltip'];
					$updateset[] = "tooltip = " . sqlesc($tooltip);
				}
				if ($enablead_advertisement == 'yes' && !$showaddisabled){
					$noad = ($_POST['showad'] == 'yes' ? "no" : "yes");
					$updateset[] = "noad = " . sqlesc($noad);
				}
				$timetype = $_POST['timetype'];
				$updateset[] = "timetype = " . sqlesc($timetype);

				$appendsticky = ($_POST["appendsticky"] == 'yes' ? "yes" : "no");
				$updateset[] = "appendsticky = " . sqlesc($appendsticky);
				$appendnew = ($_POST["appendnew"] == 'yes' ? "yes" : "no");
				$updateset[] = "appendnew = " . sqlesc($appendnew);
				$appendpromotion = $_POST["appendpromotion"];
				$updateset[] = "appendpromotion = " . sqlesc($appendpromotion);
				$appendpicked = ($_POST["appendpicked"] == 'yes' ? "yes" : "no");
				$updateset[] = "appendpicked = " . sqlesc($appendpicked);
				$dlicon = ($_POST['dlicon'] == 'yes' ? "yes" : "no");
				$updateset[] = "dlicon = " . sqlesc($dlicon);
				$bmicon = ($_POST['bmicon'] == 'yes' ? "yes" : "no");
				$updateset[] = "bmicon = " . sqlesc($bmicon);

				$showcomnum = ($_POST["showcomnum"] == 'yes' ? "yes" : "no");
				$updateset[] = "showcomnum = " . sqlesc($showcomnum);
				if ($showtooltipsetting){
					$showlastcom = ($_POST["showlastcom"] == 'yes' ? "yes" : "no");
					$updateset[] = "showlastcom = " . sqlesc($showlastcom);
				}
				$pmnum = ($_POST["pmnum"] < 1 || $_POST["pmnum"] > 100 ? 20 : floor($_POST["pmnum"]));
				$updateset[] = "pmnum = " . $pmnum;
				if ($showfunbox_main == 'yes'){$showfb = ($_POST["showfb"] == 'yes' ? "yes" : "no");
				$updateset[] = "showfb = " . sqlesc($showfb);}
				$sbnum = ($_POST["sbnum"] ? max(10, min(500, intval($_POST["sbnum"] ?? 0))) : 70);
				$updateset[] = "sbnum = " . $sbnum;
				$sbrefresh = ($_POST["sbrefresh"] ? max(10, min(3600, intval($_POST["sbrefresh"] ?? 0))) : 120);
				$updateset[] = "sbrefresh = " . $sbrefresh;

				if (isset($_POST["hidehb"]) && $_POST["hidehb"] == 'yes')
					$hidehb = 'yes';
				else $hidehb = 'no';
				$updateset[] = "hidehb = " . sqlesc($hidehb);
				if ($showextinfo['imdb'] == 'yes'){if ($_POST["showimdb"] == 'yes')
					$showimdb = 'yes';
				else $showimdb = 'no';
				$updateset[] = "showimdb = " . sqlesc($showimdb);}
				if ($_POST["showdescription"] == 'yes')
					$showdescription = 'yes';
				else $showdescription = 'no';
				$updateset[] = "showdescription = " . sqlesc($showdescription);
				if ($enablenfo_main == 'yes'){
				if (isset($_POST["shownfo"]) && $_POST["shownfo"] == 'yes')
					$shownfo = 'yes';
				else $shownfo = 'no';
				$updateset[] = "shownfo = " . sqlesc($shownfo);
				}
				if ($_POST["smalldescr"] == 'yes')
					$showsmalldescr = 'yes';
				else $showsmalldescr = 'no';
				$updateset[] = "showsmalldescr = " . sqlesc($showsmalldescr);
				if ($_POST["showcomment"] == 'yes')
					$showcomment = 'yes';
				else $showcomment = 'no';
				$updateset[] = "showcomment = " . sqlesc($showcomment);

				$query = "UPDATE users SET " . implode(",", $updateset) . " WHERE id =".sqlesc($CURUSER["id"]);
				//stderr("",$query);
				$result = sql_query($query) or sqlerr(__FILE__,__LINE__);
				header("Location: usercp.php?action=tracker&type=saved");
			}
			stdhead($lang_usercp['head_control_panel'].$lang_usercp['head_tracker_settings']);
			usercpmenu ("tracker");
$brsectiontype = $browsecatmode;
$spsectiontype = $specialcatmode;
if ($enablespecial == 'yes' && get_user_class() >= get_setting('authority.view_special_torrent'))
	$allowspecial = true;
else $allowspecial = false;
$showsubcat = (get_searchbox_value($brsectiontype, 'showsubcat') || ($allowspecial && get_searchbox_value($spsectiontype, 'showsubcat')));
$showsource = (get_searchbox_value($brsectiontype, 'showsource') || ($allowspecial && get_searchbox_value($spsectiontype, 'showsource'))); //whether show sources or not
$showmedium = (get_searchbox_value($brsectiontype, 'showmedium') || ($allowspecial && get_searchbox_value($spsectiontype, 'showmedium'))); //whether show media or not
$showcodec = (get_searchbox_value($brsectiontype, 'showcodec') || ($allowspecial && get_searchbox_value($spsectiontype, 'showcodec'))); //whether show codecs or not
$showstandard = (get_searchbox_value($brsectiontype, 'showstandard') || ($allowspecial && get_searchbox_value($spsectiontype, 'showstandard'))); //whether show standards or not
$showprocessing = (get_searchbox_value($brsectiontype, 'showprocessing') || ($allowspecial && get_searchbox_value($spsectiontype, 'showprocessing'))); //whether show processings or not
$showteam = (get_searchbox_value($brsectiontype, 'showteam') || ($allowspecial && get_searchbox_value($spsectiontype, 'showteam'))); //whether show teams or not
$showaudiocodec = (get_searchbox_value($brsectiontype, 'showaudiocodec') || ($allowspecial && get_searchbox_value($spsectiontype, 'showaudiocodec'))); //whether show audio codecs or not
$brcatsperror = get_searchbox_value($brsectiontype, 'catsperrow');
$catsperrow = get_searchbox_value($spsectiontype, 'catsperrow');
$catsperrow = (!$allowspecial ? $brcatsperror : ($catsperrow > $catsperrow ? $catsperrow : $catsperrow)); //show how many cats per line

$brcatpadding = get_searchbox_value($brsectiontype, 'catpadding');
$spcatpadding = get_searchbox_value($spsectiontype, 'catpadding');
$catpadding = (!$allowspecial ? $brcatpadding : ($brcatpadding < $spcatpadding ? $brcatpadding : $spcatpadding)); //padding space between categories in pixel

$brcats = genrelist($brsectiontype);
$spcats = genrelist($spsectiontype);

if ($showsubcat){
if ($showsource) $sources = searchbox_item_list("sources");
if ($showmedium) $media = searchbox_item_list("media");
if ($showcodec) $codecs = searchbox_item_list("codecs");
if ($showstandard) $standards = searchbox_item_list("standards");
if ($showprocessing) $processings = searchbox_item_list("processings");
if ($showteam) $teams = searchbox_item_list("teams");
if ($showaudiocodec) $audiocodecs = searchbox_item_list("audiocodecs");
}
			print ("<table border=0 cellspacing=0 cellpadding=5 width=1200>");
			form ("tracker");
			if ($type == 'saved')
				print("<tr><td colspan=2 class=\"heading\" valign=\"top\" align=\"center\"><font color=red>".$lang_usercp['text_saved']."</font></td></tr>\n");
			if ($emailnotify_smtp=='yes' && $smtptype != 'none')
				tr_small($lang_usercp['row_email_notification'], "<input type=checkbox name=pmnotif" . (strpos($CURUSER['notifs'], "[pm]") !== false ? " checked" : "") . " value=yes> ".$lang_usercp['checkbox_notification_received_pm']."<br />\n<input type=checkbox name=emailnotif" . (strpos($CURUSER['notifs'], "[email]") !== false ? " checked" : "") . " value=\"yes\" /> ".$lang_usercp['checkbox_notification_default_categories'], 1);
            //no this option
			$brenablecatrow = false;
			$categories = "<table>".($allowspecial ? "<tr><td class=embedded align=left><font class=big>".$lang_usercp['text_at_browse_page']."</font></td></tr></table><table>" : "")."<tr><td class=embedded align=left><b>".($brenablecatrow == true ? $brcatrow[0] : $lang_usercp['text_category'])."</b></td></tr><tr>";
			$i = 0;
			foreach ($brcats as $cat)//print category list of Torrents section
			{
				$numinrow = $i % $catsperrow;
				$rownum = (int)($i / $catsperrow);
				if ($i && $numinrow == 0){
					$categories .= "</tr>".($brenablecatrow ? "<tr><td class=embedded align=left><b>".$brcatrow[$rownum]."</b></td></tr>" : "")."<tr>";
				}
				$categories .= "<td align=left class=bottom style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input class=checkbox name=cat".$cat['id']." type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[cat".$cat['id']."]") !== false ? " checked" : "")." value='yes'>".return_category_image($cat['id'], "torrents.php?allsec=1&amp;")."</td>\n";
				$i++;
			}
			$categories .= "</tr>";
			if ($allowspecial) //print category list of Special section
			{
				$categories .= "</table><table><tr><td class=embedded align=left><font class=big>".$lang_usercp['text_at_special_page']."</font></td></tr></table><table>";
				$categories .= "<tr><td class=embedded align=left><b>".($spenablecatrow == true ? $spcatrow[0] : $lang_usercp['text_category'])."</b></td></tr><tr>";
				$i = 0;
				foreach ($spcats as $cat)
				{
					$numinrow = $i % $catsperrow;
					$rownum = (int)($i / $catsperrow);
					if ($i && $numinrow == 0){
						$categories .= "</tr>".($spenablecatrow ? "<tr><td class=embedded align=left><b>".$spcatrow[$rownum]."</b></td></tr>" : "")."<tr>";
					}
//					$categories .= "<td align=left class=bottom style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input class=checkbox name=cat".$cat['id']." type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[cat".$cat['id']."]") !== false ? " checked" : "")." value='yes'><img src=pic/" .get_cat_folder($cat['id']). '/'. htmlspecialchars($cat['image']) . " border='0' alt=\"" .$cat['name']."\" title=\"" .$cat['name']."\"></td>\n";
					$categories .= "<td align=left class=bottom style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input class=checkbox name=cat".$cat['id']." type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[cat".$cat['id']."]") !== false ? " checked" : "")." value='yes'>" . return_category_image($cat['id'], "special.php?allsec=1&amp;") . "</td>\n";
					$i++;
				}
			$categories .= "</tr>";
			}
			if ($showsubcat)//Show subcategory (i.e. source, codecs) selections
			{
				$categories .= "</table><table><tr><td class=embedded align=left><font class=big>".$lang_usercp['text_sub_category']."</font></td></tr></table><table>";
				if ($showsource){
				$categories .= "<tr><td class=embedded align=left><b>".$lang_usercp['text_source']."</b></td></tr><tr>";
				$i = 0;
				foreach ($sources as $source)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=left class=bottom style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input class=checkbox name=sou{$source['id']} type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[sou".$source['id']."]") !== false ? " checked" : "") . " value='yes'>{$source['name']}</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showmedium){
				$categories .= "<tr><td class=embedded align=left><b>".$lang_usercp['text_medium']."</b></td></tr><tr>";
				$i = 0;
				foreach ($media as $medium)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=left class=bottom style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input class=checkbox name=med{$medium['id']} type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[med".$medium['id']."]") !== false ? " checked" : "") . " value='yes'>{$medium['name']}</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showcodec){
				$categories .= "<tr><td class=embedded align=left><b>".$lang_usercp['text_codec']."</b></td></tr><tr>";
				$i = 0;
				foreach ($codecs as $codec)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=left class=bottom style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input class=checkbox name=cod{$codec['id']} type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[cod".$codec['id']."]") !== false ? " checked" : "") . " value='yes'>{$codec['name']}</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showaudiocodec){
				$categories .= "<tr><td class=embedded align=left><b>".$lang_usercp['text_audio_codec']."</b></td></tr><tr>";
				$i = 0;
				foreach ($audiocodecs as $audiocodec)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=left class=bottom style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input class=checkbox name=aud{$audiocodec['id']} type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[aud".$audiocodec['id']."]") !== false ? " checked" : "") . " value='yes'>{$audiocodec['name']}</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showstandard){
				$categories .= "<tr><td class=embedded align=left><b>".$lang_usercp['text_standard']."</b></td></tr><tr>";
				$i = 0;
				foreach ($standards as $standard)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=left class=bottom style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input class=checkbox name=sta{$standard['id']} type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[sta".$standard['id']."]") !== false ? " checked" : "") . " value='yes'>{$standard['name']}</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showprocessing){
				$categories .= "<tr><td class=embedded align=left><b>".$lang_usercp['text_processing']."</b></td></tr><tr>";
				$i = 0;
				foreach ($processings as $processing)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=left class=bottom style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input class=checkbox name=pro{$processing['id']} type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[pro".$processing['id']."]") !== false ? " checked" : "") . " value='yes'>{$processing['name']}</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showteam){
				$categories .= "<tr><td class=embedded align=left><b>".$lang_usercp['text_team']."</b></td></tr><tr>";
				$i = 0;
				foreach ($teams as $team)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=left class=bottom style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input class=checkbox name=tea{$team['id']} type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[tea".$team['id']."]") !== false ? " checked" : "") . " value='yes'>{$team['name']}</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
			}
			$categories .= "</table><table>";
			$categories .= "<tr><td colspan=3 class=embedded align=left><font class=big>".$lang_usercp['text_additional_selection']."</font></td></tr>";

	if (strpos($CURUSER['notifs'], "[spstate=0]") !== false)
		$special_state = 0;
	elseif (strpos($CURUSER['notifs'], "[spstate=1]") !== false)
		$special_state = 1;
	elseif (strpos($CURUSER['notifs'], "[spstate=2]") !== false)
		$special_state = 2;
	elseif (strpos($CURUSER['notifs'], "[spstate=3]") !== false)
		$special_state = 3;
	elseif (strpos($CURUSER['notifs'], "[spstate=4]") !== false)
		$special_state = 4;
	elseif (strpos($CURUSER['notifs'], "[spstate=5]") !== false)
		$special_state = 5;
	elseif (strpos($CURUSER['notifs'], "[spstate=6]") !== false)
		$special_state = 6;
	else $special_state = 0;

			$categories .= "<tr><td class=bottom><b>".$lang_usercp['text_show_dead_active']."</b><br /><select name=\"incldead\"><option value=\"0\" ".(strpos($CURUSER['notifs'], "[incldead=0]") !== false ? " selected" : "").">".$lang_usercp['select_including_dead']."</option><option value=\"1\" ".(strpos($CURUSER['notifs'], "[incldead=1]") !== false ||  strpos($CURUSER['notifs'], "incldead") == false ? " selected" : "").">".$lang_usercp['select_active']."</option><option value=\"2\" ".(strpos($CURUSER['notifs'], "[incldead=2]") !== false  ? " selected" : "").">".$lang_usercp['select_dead']."</option></select></td><td class=bottom align=left><b>".$lang_usercp['text_show_special_torrents']."</b><br /><select name=\"spstate\"><option value=\"0\" ".($special_state == 0 ? " selected" : "").">".$lang_usercp['select_all']."</option>".promotion_selection($special_state)."</select></td><td class=bottom><b>".$lang_usercp['text_show_bookmarked']."</b><br /><select name=\"inclbookmarked\"><option value=\"0\" ".(strpos($CURUSER['notifs'], "[inclbookmarked=0]") !== false ? " selected" : "").">".$lang_usercp['select_all']."</option><option value=\"1\" ".(strpos($CURUSER['notifs'], "[inclbookmarked=1]") !== false ? " selected" : "")." >".$lang_usercp['select_bookmarked']."</option><option value=\"2\" ".(strpos($CURUSER['notifs'], "[inclbookmarked=2]") !== false ? " selected" : "").">".$lang_usercp['select_bookmarked_exclude']."</option></select></td></tr>";
			$categories .= "</table>";
			tr_small($lang_usercp['row_browse_default_categories'],$categories,1);
			$ss_r = sql_query("SELECT * FROM stylesheets") or die;
			$ss_sa = array();
			while ($ss_a = mysql_fetch_array($ss_r))
			{
				$ss_id = $ss_a["id"];
				$ss_name = $ss_a["name"];
				$ss_sa[$ss_name] = $ss_id;
			}
			ksort($ss_sa);
			reset($ss_sa);
            $stylesheets = $categoryicons = '';
//			while (list($ss_name, $ss_id) = each($ss_sa))
            foreach ($ss_sa as $ss_name => $ss_id)
			{
				if ($ss_id == $CURUSER["stylesheet"]) $ss = " selected"; else $ss = "";
				$stylesheets .= "<option value=$ss_id$ss>$ss_name</option>\n";
			}
            tr_small($lang_usercp['row_stylesheet'], "<select name=stylesheet>\n$stylesheets\n</select>&nbsp;&nbsp;<font class=small>".$lang_usercp['text_stylesheet_note']."<a href=\"aboutnexus.php#stylesheet\" ><b>".$lang_usercp['text_stylesheet_link']."</b></a></font>.",1);
            /**
             * make no sense, should no exists!
             * @since v1.6.0
             */
//			$cires = sql_query("SELECT * FROM caticons ORDER BY name") or die;
//			while($caticon = mysql_fetch_array($cires)){
//				if ($caticon['id'] == $CURUSER['caticon']) $sl = " selected"; else $sl = "";
//				$categoryicons .= "<option value=".$caticon['id'].$sl.">".$caticon['name']."</option>\n";
//			}
//			tr_small($lang_usercp['row_category_icons'], "<select name=caticon>".$categoryicons."</select>",1);

            tr_small($lang_usercp['row_font_size'], "<select name=fontsize><option value=small ".($CURUSER['fontsize'] == 'small' ? " selected" : "").">".$lang_usercp['select_small']."</option><option value=medium ".($CURUSER['fontsize'] == 'medium' ? " selected" : "").">".$lang_usercp['select_medium']."</option><option value=large ".($CURUSER['fontsize'] == 'large' ? " selected" : "").">".$lang_usercp['select_large']."</option></select>",1);

			$s = "<select name=\"sitelanguage\">\n";

			$langs = langlist("site_lang");

			foreach ($langs as $row)
			{
				if ($row["site_lang_folder"] == get_langfolder_cookie()) $se = " selected"; else $se = "";
				$s .= "<option value=". $row["id"] . $se. ">" . htmlspecialchars($row["lang_name"]) . "</option>\n";
			}
			$s .= "\n</select>&nbsp;&nbsp;<font class=small>".$lang_usercp['text_translation_note']."<a href=\"aboutnexus.php#translation\"><b>".$lang_usercp['text_translation_link']."</b></a></font>.</td></tr>";

			tr_small($lang_usercp['row_site_language'], $s,1);

			if($showmovies['hot'] == "yes" || $showmovies['classic'] == "yes")
			tr_small($lang_usercp['row_recommended_movies'], ($showmovies['hot'] == "yes" ? "<input type=checkbox name=show_hot" . ($CURUSER["showhot"] == "yes" ? " checked" : "") . " value=yes>".$lang_usercp['checkbox_show_hot']. "&nbsp;" : "") . ($showmovies['classic'] == "yes" ? "<input type=checkbox name=show_classic" . ($CURUSER["showclassic"] == "yes" ? " checked" : "") . " value=yes>".$lang_usercp['checkbox_show_classic']."&nbsp;" : ""),1);
			tr_small($lang_usercp['row_pm_boxes'], $lang_usercp['text_show']."<input type=text name=pmnum size=5 value=".$CURUSER['pmnum']." >".$lang_usercp['text_pms_per_page'], 1);
if ($showshoutbox_main == "yes") //system side setting for shoutbox
			tr_small($lang_usercp['row_shoutbox'], $lang_usercp['text_show_last']."<input type=text name=sbnum size=5 value=".$CURUSER['sbnum']." >".$lang_usercp['text_messages_at_shoutbox']."<br />".$lang_usercp['text_refresh_shoutbox_every']."<input type=text name=sbrefresh size=5 value=".$CURUSER['sbrefresh']." >".$lang_usercp['text_seconds'].($showhelpbox_main == 'yes' ? "<br /><input type=checkbox name=hidehb".($CURUSER["hidehb"] == "yes" ? " checked" : "") ." value=yes>".$lang_usercp['text_hide_helpbox_messages'] : ""), 1);
if ($showfunbox_main == 'yes') //siteside setting for funbox
tr_small($lang_usercp['row_funbox'],"<input type=checkbox name=showfb".($CURUSER["showfb"] == "yes" ? " checked" : "") ." value=yes>".$lang_usercp['text_show_funbox'] , 1);

			tr_small($lang_usercp['row_torrent_detail'], "<input type=checkbox name=showdescription".($CURUSER["showdescription"] == "yes" ? " checked" : "") ." value=yes>".$lang_usercp['text_show_description']."<br />".($enablenfo_main == 'yes' && get_user_class() >= UC_POWER_USER ? "<input type=checkbox name=shownfo".($CURUSER["shownfo"] == "yes" ? " checked" : "") ." value=yes>".$lang_usercp['text_show_nfo']."<br />" : "").($showextinfo['imdb'] == 'yes' ? "<input type=checkbox name=showimdb".($CURUSER["showimdb"] == "yes" ? " checked" : "") ." value=yes>".$lang_usercp['text_show_imdb_info'] : ""),1);
			tr_small($lang_usercp['row_discuss'],"<input type=checkbox name=showcomment".($CURUSER["showcomment"] == "yes" ? " checked" : "") ." value=yes>".$lang_usercp['text_show_comments'], 1);
			if ($enablead_advertisement == 'yes'){
				tr_small($lang_usercp['row_show_advertisements'],"<input type=\"checkbox\" name=\"showad\"".($CURUSER["noad"] == "yes" ? "" : " checked=\"checked\"") .($showaddisabled ? " disabled=\"disabled\"" : ""). " value=\"yes\" />".$lang_usercp['text_show_advertisement_note'].($enablenoad_advertisement == 'yes' ? "<br />".get_user_class_name($noad_advertisement,false,true,true).$lang_usercp['text_can_turn_off_advertisement'] : "").($enablebonusnoad_advertisement == 'yes' ? "<br />".get_user_class_name($bonusnoad_advertisement,false,true,true).$lang_usercp['text_buy_no_advertisement']."<a href=\"mybonus.php\"><b>".$lang_usercp['text_bonus_center']."</b></a>" : ""), 1);
			}
			tr_small($lang_usercp['row_time_type'], "<input type=radio name=timetype ".($CURUSER['timetype'] == 'timeadded' ? " checked" : "")." value=timeadded>".$lang_usercp['text_time_added']."&nbsp;&nbsp;<input type=radio name=timetype ".($CURUSER['timetype'] == 'timealive' ? " checked" : "")." value=timealive>".$lang_usercp['text_time_elapsed']."<br />", 1);
			//Setting for browse page
			tr_small($lang_usercp['row_browse_page'], $lang_usercp['text_browse_setting_warning']."
		<br /><b>".$lang_usercp['row_torrent_page'].": </b><br />".$lang_usercp['text_show']."<input type=text size=5 name=torrentsperpage value=".$CURUSER['torrentsperpage']."> ".$lang_usercp['text_torrents_per_page'].$lang_usercp['text_zero_equals_default']."<br />".
		($showtooltipsetting ? "<b>".$lang_usercp['text_tooltip_type']."</b>: <br />".($showextinfo['imdb'] == 'yes' ? "<input type=radio name=tooltip ".($CURUSER['tooltip'] == 'minorimdb' ? " checked" : "")." value=minorimdb>".$lang_usercp['text_minor_imdb_info']."<br /><input type=radio name=tooltip ".($CURUSER['tooltip'] == 'medianimdb' ? " checked" : "")." value=medianimdb>".$lang_usercp['text_median_imdb_info']. "<br />" : "")."<input type=radio name=tooltip ".($CURUSER['tooltip'] == 'off' ? " checked" : "")." value=off>".$lang_usercp['text_off']."<br />" : "").
		"<b>".$lang_usercp['text_append_words_to_torrents'].": </b><br /><input type=checkbox name=appendsticky ".($CURUSER['appendsticky'] == 'yes' ? " checked" : "")." value=yes>".$lang_usercp['text_append_sticky']."<br /><input type=checkbox name=appendnew ".($CURUSER['appendnew'] == 'yes' ? " checked" : "")." value=yes>".$lang_usercp['text_append_new']."<br />".$lang_usercp['text_torrents_on_promotion']."<input type=radio name=appendpromotion ".($CURUSER['appendpromotion'] == 'highlight' ? " checked" : "")." value='highlight'>".$lang_usercp['text_highlight']."<input type=radio name=appendpromotion ".($CURUSER['appendpromotion'] == 'word' ? " checked" : "")." value='word'>".$lang_usercp['text_append_words']."<input type=radio name=appendpromotion ".($CURUSER['appendpromotion'] == 'icon' ? " checked" : "")." value='icon'>".$lang_usercp['text_append_icon']."<input type=radio name=appendpromotion ".($CURUSER['appendpromotion'] == 'off' ? " checked" : "")." value='off'>".$lang_usercp['text_no_mark']."<br /><input type=checkbox name=appendpicked ".($CURUSER['appendpicked'] == 'yes' ? " checked" : "")." value=yes>".$lang_usercp['text_append_picked']."<br />
		<b>".$lang_usercp['text_show_title'].": </b><br />"."<input type=checkbox name=smalldescr ".($CURUSER['showsmalldescr'] == 'yes' ? " checked" : "")." value=yes>".$lang_usercp['text_show_small_description']."<br />
		<b>".$lang_usercp['text_show_action_icons'].": </b><br />"."<input type=checkbox name=dlicon ".($CURUSER['dlicon'] == 'yes' ? " checked" : "")." value=yes>".$lang_usercp['text_show_download_icon']." <img class=\"download\" src=\"pic/trans.gif\"  alt=\"Download\" /><br /><input type=checkbox name=bmicon ".($CURUSER['bmicon'] == 'yes' ? " checked" : "")." value=yes>".$lang_usercp['text_show_bookmark_icon']." <img class=\"bookmark\" src=\"pic/trans.gif\" alt=\"Bookmark\" /><br />
		<b>".$lang_usercp['text_comments_reviews'].": </b><br /><input type=checkbox name=showcomnum ".($CURUSER['showcomnum'] == 'yes' ? " checked" : "")." value=yes>".$lang_usercp['text_show_comment_number'].($showtooltipsetting ? "<select name=\"showlastcom\" style=\"width: 70px;\"><option value=\"yes\" ".($CURUSER['showlastcom'] != 'no' ? " selected" : "").">".$lang_usercp['select_with']."</option><option value=\"no\" ".($CURUSER['showlastcom'] == 'no' ? " selected" : "").">".$lang_usercp['select_without']."</option></select>".$lang_usercp['text_last_comment_on_tooltip'] : ""), 1);

			submit();
			print("</table>");
			stdfoot();
			die;
			break;
		case "forum":
			if ($enabletooltip_tweak == 'yes')
				$showtooltipsetting = true;
			else
				$showtooltipsetting = false;
			if ($type == 'save') {
				$updateset = array();
				$avatars = ($_POST["avatars"] != "" ? "yes" : "no");
				$ttlastpost = ($_POST["ttlastpost"] != "" ? "yes" : "no");
				$signatures = ($_POST["signatures"] != "" ? "yes" : "no");
				$signature = htmlspecialchars( trim($_POST["signature"]) );

				$updateset[] = "topicsperpage = " . min(100, intval($_POST["topicsperpage"] ?? 0));
				$updateset[] = "postsperpage = " . min(100, intval($_POST["postsperpage"] ?? 0));
				$updateset[] = "avatars = " . sqlesc($avatars);
				if ($showtooltipsetting)
					$updateset[] = "showlastpost = " . sqlesc($ttlastpost);
				$updateset[] = "signatures = " . sqlesc($signatures);
				$clicktopic = $_POST["clicktopic"];
				$updateset[] = "clicktopic = ".sqlesc($clicktopic);
				$updateset[] = "signature = " . sqlesc($signature);

				$query = "UPDATE users SET " . implode(",", $updateset) . " WHERE id =".sqlesc($CURUSER["id"]);
				$result = sql_query($query);
				if (!$result)
				sqlerr(__FILE__,__LINE__);
				else
				header("Location: usercp.php?action=forum&type=saved");
			}
			stdhead($lang_usercp['head_control_panel'].$lang_usercp['head_forum_settings'],true);
			usercpmenu ("forum");
			print ("<table border=0 cellspacing=0 cellpadding=5 width=1200>");
			form ("forum");
			if ($type == 'saved')
			print("<tr><td colspan=2 class=\"heading\" valign=\"top\" align=\"center\"><font color=red>".$lang_usercp['text_saved']."</font></td></tr>\n");

			tr_small($lang_usercp['row_topics_per_page'], "<input type=text size=10 name=topicsperpage value=$CURUSER[topicsperpage]>".$lang_usercp['text_zero_equals_default'],1);
			tr_small($lang_usercp['row_posts_per_page'], "<input type=text size=10 name=postsperpage value=$CURUSER[postsperpage]> ".$lang_usercp['text_zero_equals_default'],1);
			tr_small($lang_usercp['row_view_avatars'], "<input type=checkbox name=avatars" . ($CURUSER["avatars"] == "yes" ? " checked" : "") . ">".$lang_usercp['checkbox_low_bandwidth_note'],1);
			tr_small($lang_usercp['row_view_signatures'], "<input type=checkbox name=signatures" . ($CURUSER["signatures"] == "yes" ? " checked" : "") . ">".$lang_usercp['checkbox_low_bandwidth_note'],1);
			if ($showtooltipsetting)
				tr($lang_usercp['row_tooltip_last_post'], "<input type=checkbox name=ttlastpost" . ($CURUSER["showlastpost"] == "yes" ? " checked" : "") . ">".$lang_usercp['checkbox_last_post_note'],1);
			tr_small($lang_usercp['row_click_on_topic'], "<input type=radio name=clicktopic" . ($CURUSER["clicktopic"] == "firstpage" ? " checked" : "") . " value=\"firstpage\">".$lang_usercp['text_go_to_first_page']."<input type=radio name=clicktopic" . ($CURUSER["clicktopic"] == "lastpage" ? " checked" : "") . " value=\"lastpage\">".$lang_usercp['text_go_to_last_page'],1);
			tr_small($lang_usercp['row_forum_signature'], "<textarea name=signature style=\"width:700px\" rows=10>" . $CURUSER['signature'] . "</textarea><br />".$lang_usercp['text_signature_note'],1);
			submit();
			print("</table>");
			stdfoot();
			die;
			break;
		case "security":
			if ($type == 'confirm') {
				$oldpassword = $_POST['oldpassword'];
				if (!$oldpassword){
					stderr($lang_usercp['std_error'], $lang_usercp['std_enter_old_password'].goback(), 0);
					die;
				}elseif ($CURUSER["passhash"] != md5($CURUSER["secret"] . $oldpassword . $CURUSER["secret"])){
					stderr($lang_usercp['std_error'], $lang_usercp['std_wrong_password_note'].goback(), 0);
					die;
				}else
				$updateset = array();
				$changedemail = 0;
				$passupdated = 0;
				$privacyupdated = 0;
				$resetpasskey = $_POST["resetpasskey"];
				$email = mysql_real_escape_string( htmlspecialchars( trim($_POST["email"]) ));
				$chpassword = $_POST["chpassword"];
				$passagain = $_POST["passagain"];
				$privacy = $_POST["privacy"];
				$twoStepSecret = $_POST['two_step_secret'] ?? '';
				$twoStepSecretHash = $_POST['two_step_code'];

				if (!empty($twoStepSecretHash)) {
                    $ga = new \PHPGangsta_GoogleAuthenticator();
				    if (empty($CURUSER['two_step_secret'])) {
				        //do bind
                        $secretToVerify = $twoStepSecret;
                        $updateset[] = "two_step_secret = " . sqlesc($twoStepSecret);
                    } else {
				        //unbind
                        $secretToVerify = $CURUSER['two_step_secret'];
                        $updateset[] = "two_step_secret = ''";
                    }
                    if (!$ga->verifyCode($secretToVerify, $twoStepSecretHash)) {
                        stderr($lang_usercp['std_error'], 'Invalid two step code'.goback("-2"), 0);
                        die;
                    }
                }

				if ($chpassword != "") {
					if ($chpassword == $CURUSER["username"]) {
						stderr($lang_usercp['std_error'], $lang_usercp['std_password_equals_username'].goback("-2"), 0);
						die;
					}
					if (strlen($chpassword) > 40) {
						stderr($lang_usercp['std_error'], $lang_usercp['std_password_too_long'].goback("-2"), 0);
						die;
					}
					if (strlen($chpassword) < 6) {
						stderr($lang_usercp['std_error'], $lang_usercp['std_password_too_short'].goback("-2"), 0);
						die;
					}
					if ($chpassword != $passagain) {
						stderr($lang_usercp['std_error'], $lang_usercp['std_passwords_unmatched'].goback("-2"), 0);
						die;
					}

					$sec = mksecret();
					$passhash = md5($sec . $chpassword . $sec);
					$updateset[] = "secret = " . sqlesc($sec);
					$updateset[] = "passhash = " . sqlesc($passhash);

					//die($securelogin . base64_decode($_COOKIE["c_secure_login"]));
					if ($_COOKIE["c_secure_login"] == base64("yeah"))
					{
						$passh = md5($passhash . $_SERVER["REMOTE_ADDR"]);
						$securelogin_indentity_cookie = true;
					}
					else
					{
						$passh = md5($passhash);
						$securelogin_indentity_cookie = false;
					}

					if($_COOKIE["c_secure_ssl"] == base64("yeah"))
						$ssl = true;
					else
						$ssl = false;

					logincookie($CURUSER["id"], $passh ,1,0x7fffffff,$securelogin_indentity_cookie,$ssl);
					//sessioncookie($CURUSER["id"], $passh);
					$passupdated = 1;
				}

				if ($disableemailchange != 'no' && $smtptype != 'none' && $email != $CURUSER["email"])
				{
					if(EmailBanned($email))
					bark($lang_usercp['std_email_address_banned']);

					if(!EmailAllowed($email))
					bark($lang_usercp['std_wrong_email_address_domains'].allowedemails());

					if (!validemail($email)){
						stderr($lang_usercp['std_error'], $lang_usercp['std_wrong_email_address_format'].goback("-2"), 0);
						die;
					}
					$r = sql_query("SELECT id FROM users WHERE email=" . sqlesc($email)) or sqlerr();
					if (mysql_num_rows($r) > 0){
						stderr($lang_usercp['std_error'], $lang_usercp['std_email_in_use'].goback("-2"), 0);
						die;
					}
					$changedemail = 1;
				}
				if ($resetpasskey == 1) {
					$passkey = md5($CURUSER['username'].date("Y-m-d H:i:s").$CURUSER['passhash']);
					$updateset[] = "passkey = " . sqlesc($passkey);
				}
				if ($changedemail == 1) {
					$sec = mksecret();
					$hash = md5($sec . $email . $sec);
					$obemail = rawurlencode($email);
					$updateset[] = "editsecret = " . sqlesc($sec);
					$subject = "$SITENAME".$lang_usercp['mail_profile_change_confirmation'];
					$body = <<<EOD
{$lang_usercp['mail_change_email_one']}{$CURUSER["username"]}{$lang_usercp['mail_change_email_two']}($email){$lang_usercp['mail_change_email_three']}

{$lang_usercp['mail_change_email_four']}{$_SERVER["REMOTE_ADDR"]}{$lang_usercp['mail_change_email_five']}

{$lang_usercp['mail_change_email_six']}<b><a href="javascript:void(null)" onclick="window.open('http://$BASEURL/confirmemail.php/{$CURUSER["id"]}/$hash/$obemail')">{$lang_usercp['mail_here']}</a></b>{$lang_usercp['mail_change_email_six_1']}<br />
http://$BASEURL/confirmemail.php/{$CURUSER["id"]}/$hash/$obemail

{$lang_usercp['mail_change_email_seven']}

------{$lang_usercp['mail_change_email_eight']}
{$lang_usercp['mail_change_email_nine']}
EOD;

					sent_mail($email,$SITENAME,$SITEEMAIL,$subject,str_replace("<br />","<br />",nl2br($body)),"profile change",false,false,'');

				}
				if ($privacy != "normal" && $privacy != "low" && $privacy != "strong")
				die("whoops");

				$updateset[] = "privacy = " . sqlesc($privacy);
				if ($CURUSER['privacy'] != $privacy) $privacyupdated = 1;

				$user = $CURUSER["id"];
				$query = sprintf("UPDATE users SET " . implode(",", $updateset) . " WHERE id ='%s'",
				mysql_real_escape_string($user));
				$result = sql_query($query);
				if (!$result)
				sqlerr(__FILE__,__LINE__);

				if (!empty($_REQUEST['resetauthkey']) && $_REQUEST['resetauthkey'] == 1) {
				    //reset authkey
				    $torrentRep = new \App\Repositories\TorrentRepository();
				    $torrentRep->resetTrackerReportAuthKeySecret($user);
                }
				$to = "usercp.php?action=security&type=saved";
				if ($changedemail == 1)
				$to .= "&mail=1";
				if ($resetpasskey == 1)
				$to .= "&passkey=1";
				if ($passupdated == 1)
				$to .= "&password=1";
				if ($privacyupdated == 1)
				$to .= "&privacy=1";
				header("Location: $to");
			}
			stdhead($lang_usercp['head_control_panel'].$lang_usercp['head_security_settings']);
			usercpmenu ("security");
			print ("<table border=0 cellspacing=0 cellpadding=5 width=1200>");
			if ($type == 'save') {
				print("<form method=post action=usercp.php><input type=hidden name=action value=security><input type=hidden name=type value=confirm>");
				$resetpasskey = $_POST["resetpasskey"];
				$resetauthkey = $_POST["resetauthkey"];
				$email = mysql_real_escape_string( htmlspecialchars( trim($_POST["email"]) ));
				$chpassword = $_POST["chpassword"];
				$passagain = $_POST["passagain"];
				$privacy = $_POST["privacy"];
				$two_step_secret = $_POST["two_step_secret"] ?? '';
				$two_step_code = $_POST["two_step_code"];
				if ($resetpasskey == 1)
				print("<input type=\"hidden\" name=\"resetpasskey\" value=\"1\">");
                if ($resetauthkey == 1)
				print("<input type=\"hidden\" name=\"resetauthkey\" value=\"1\">");
				print("<input type=\"hidden\" name=\"email\" value=\"$email\">");
				print("<input type=\"hidden\" name=\"chpassword\" value=\"$chpassword\">");
				print("<input type=\"hidden\" name=\"passagain\" value=\"$passagain\">");
				print("<input type=\"hidden\" name=\"privacy\" value=\"$privacy\">");
				print("<input type=\"hidden\" name=\"two_step_secret\" value=\"$two_step_secret\">");
				print("<input type=\"hidden\" name=\"two_step_code\" value=\"$two_step_code\">");
				Print("<tr><td class=\"rowhead nowrap\" valign=\"top\" align=\"right\" width=1%>".$lang_usercp['row_security_check']."</td><td valign=\"top\" align=\"left\" width=\"99%\"><input type=password name=oldpassword style=\"width: 200px\"><br /><font class=small>".$lang_usercp['text_security_check_note']."</font></td></tr>\n");
				submit();
				print("</table>");
				stdfoot();
				die;
			}
			if ($type == 'saved')
				print("<tr><td colspan=2 class=\"heading\" valign=\"top\" align=\"center\"><font color=red>".$lang_usercp['text_saved'].($_GET["mail"] == "1" ? $lang_usercp['std_confirmation_email_sent'] : "")." ".($_GET["passkey"] == "1" ? $lang_usercp['std_passkey_reset'] : "")." ".($_GET["password"] == "1" ? $lang_usercp['std_password_changed'] : "")." ".($_GET["privacy"] == "1" ? $lang_usercp['std_privacy_level_updated'] : "")."</font></td></tr>\n");
			form ("security");
			tr_small($lang_usercp['row_reset_passkey'],"<input type=checkbox name=resetpasskey value=1 />".$lang_usercp['checkbox_reset_my_passkey']."<br /><font class=small>".$lang_usercp['text_reset_passkey_note']."</font>", 1);
			tr_small($lang_usercp['row_reset_authkey'],"<input type=checkbox name=resetauthkey value=1 />".$lang_usercp['checkbox_reset_my_authkey']."<br /><font class=small>".$lang_usercp['text_reset_authkey_note']."</font>", 1);

			//two step authentication
            if (!empty($CURUSER['two_step_secret'])) {
                tr_small($lang_usercp['row_two_step_secret'],"<input type=text name=two_step_code />".$lang_usercp['text_two_step_secret_unbind_note'], 1);
            } else {
                $ga = new \PHPGangsta_GoogleAuthenticator();
                $twoStepSecret = $ga->createSecret();
                $twoStepQrCodeUrl = $ga->getQRCodeGoogleUrl(sprintf('%s(%s)', get_setting('basic.SITENAME'), $CURUSER['username']), $twoStepSecret);
                $twoStepY = '<div style="display: flex;align-items:center">';
                $twoStepY .= sprintf('<div><img src="%s" /></div>', $twoStepQrCodeUrl);
                $twoStepY .= sprintf(
                    '<div style="padding-left: 20px">%s<a href="%s" target="_blank">Link</a><br /><br />%s%s<br/><br/>%s<input type=hidden name=two_step_secret value="%s" /><input type=text name=two_step_code /></div>',
                    $lang_usercp['text_two_step_secret_bind_by_qrdoe_note'],
                    $twoStepQrCodeUrl,
                    $lang_usercp['text_two_step_secret_bind_manually_note'],
                    $twoStepSecret,
                    $lang_usercp['text_two_step_secret_bind_complete_note'],
                    $twoStepSecret
                );
                $twoStepY .= '</div>';
                tr_small($lang_usercp['row_two_step_secret'], $twoStepY, 1);
            }

			if ($disableemailchange != 'no' && $smtptype != 'none') //system-wide setting
				tr_small($lang_usercp['row_email_address'], "<input type=\"text\" name=\"email\" style=\"width: 200px\" value=\"" . htmlspecialchars($CURUSER["email"]) . "\" /> <br /><font class=small>".$lang_usercp['text_email_address_note']."</font>", 1);
			tr_small($lang_usercp['row_change_password'], "<input type=\"password\" name=\"chpassword\" style=\"width: 200px\" />", 1);
			tr_small($lang_usercp['row_type_password_again'], "<input type=\"password\" name=\"passagain\" style=\"width: 200px\" />", 1);
			tr_small($lang_usercp['row_privacy_level'],  priv("normal", $lang_usercp['radio_normal']) . " " . priv("low", $lang_usercp['radio_low']) . " " . priv("strong", $lang_usercp['radio_strong']), 1);
			submit();
			print("</table>");
			stdfoot();
			die;
			break;
	}
}
}

stdhead($lang_usercp['head_control_panel'].$lang_usercp['head_home']);
usercpmenu ();
//Comment Results
$commentcount = get_row_count("comments", "WHERE user=" . sqlesc($CURUSER["id"]));

//Join Date
if ($CURUSER['added'] == "0000-00-00 00:00:00" || $CURUSER['added'] == null)
	$joindate = 'N/A';
else
	$joindate = $CURUSER['added']." (" . gettime($CURUSER['added'],true,false,true).")";

//Forum Posts
if (!$forumposts = $Cache->get_value('user_'.$CURUSER['id'].'_post_count')){
	$forumposts = get_row_count("posts","WHERE userid=".$CURUSER['id']);
	$Cache->cache_value('user_'.$CURUSER['id'].'_post_count', $forumposts, 3600);
}
$dayposts = 0;
if ($forumposts)
{
	$seconds3 = (TIMENOW - strtotime($CURUSER["added"]));
	$days = round($seconds3/86400, 0);
	if($days > 1) {
		$dayposts  = round(($forumposts / $days), 1);
	}
	if (!$postcount = $Cache->get_value('total_posts_count')){
		$postcount = get_row_count("posts");
		$Cache->cache_value('total_posts_count', $postcount, 96400);
	}
	$percentages = round($forumposts*100/$postcount, 3)."%";
}
?>
<table border="0" cellspacing="0" cellpadding="5" width=1200>
<?php
tr_small($lang_usercp['row_join_date'], $joindate, 1);
tr_small($lang_usercp['row_email_address'], $CURUSER['email'], 1);
$seedBoxIcon = (new \App\Repositories\SeedBoxRepository())->renderIcon($CURUSER['ip'], $CURUSER['id']);
if ($enablelocation_tweak == 'yes'){
	list($loc_pub, $loc_mod) = get_ip_location($CURUSER["ip"]);
	tr_small($lang_usercp['row_ip_location'], $CURUSER["ip"]." <span title='" . $loc_mod . "'>[" . $loc_pub . "]</span>$seedBoxIcon", 1);
}
else{
	tr_small($lang_usercp['row_ip_location'], $CURUSER["ip"] . $seedBoxIcon, 1);
}
if ($CURUSER["avatar"])
	tr_small($lang_usercp['row_avatar'], "<img src=\"" . $CURUSER["avatar"] . "\" border=0>", 1);
tr_small($lang_usercp['row_passkey'], $CURUSER["passkey"], 1);
if (get_setting('security.login_type') == 'passkey' && get_setting('security.login_secret_deadline') > date('Y-m-d H:i:s')) {
    tr_small($lang_usercp['row_passkey_login_url'], sprintf('%s/%s/%s', getSchemeAndHttpHost(), get_setting('security.login_secret'), $CURUSER['passkey']), 1);
}
if ($prolinkpoint_bonus)
{
	$prolinkclick=get_row_count("prolinkclicks", "WHERE userid=".$CURUSER['id']);
	tr_small($lang_usercp['row_promotion_link'], $prolinkclick. " [<a href=\"promotionlink.php\">".$lang_usercp['text_read_more']."</a>]", 1);
	//tr_small($lang_usercp['row_promotion_link'], $prolinkclick. " [<a href=\"promotionlink.php?updatekey=1\">".$lang_usercp['text_update_promotion_link']."</a>] [<a href=\"promotionlink.php\">".$lang_usercp['text_read_more']."</a>]", 1);
}
tr_small($lang_usercp['row_invitations'],$CURUSER['invites']." [<a href=\"invite.php?id=".$CURUSER['id']."\" title=\"".$lang_usercp['link_send_invitation']."\">".$lang_usercp['text_send']."</a>]",1);
tr_small($lang_usercp['row_karma_points'], $CURUSER['seedbonus']." [<a href=\"mybonus.php\" title=\"".$lang_usercp['link_use_karma_points']."\">".$lang_usercp['text_use']."</a>]", 1);
tr_small($lang_usercp['row_written_comments'], $commentcount." [<a href=\"userhistory.php?action=viewcomments&id=".$CURUSER['id']."\" title=\"".$lang_usercp['link_view_comments']."\">".$lang_usercp['text_view']."</a>]", 1);

//start seed box
if (get_setting('seed_box.enabled') == 'yes') {
    $seedBox = '';
    $columnOperator = nexus_trans('label.seed_box_record.operator');
    $columnBandwidth = nexus_trans('label.seed_box_record.bandwidth');
    $columnIPBegin = nexus_trans('label.seed_box_record.ip_begin');
    $columnIPEnd = nexus_trans('label.seed_box_record.ip_end');
    $columnIP = nexus_trans('label.seed_box_record.ip');
    $columnIPHelp = nexus_trans('label.seed_box_record.ip_help');
    $columnComment = nexus_trans('label.comment');
    $columnStatus = nexus_trans('label.seed_box_record.status');
    $res = \App\Models\SeedBoxRecord::query()->where('uid', $CURUSER['id'])->where('type', \App\Models\SeedBoxRecord::TYPE_USER)->get();
    if ($res->count() > 0)
    {
        $seedBox .= "<table border='1' cellspacing='0' cellpadding='5' id='seed-box-table'><tr><td class='colhead'>ID</td><td class='colhead'>{$columnOperator}</td><td class='colhead'>{$columnBandwidth}</td><td class='colhead'>{$columnIP}</td><td class='colhead'>{$columnComment}</td><td class='colhead'>{$columnStatus}</td><td class='colhead'></td></tr>";
        foreach ($res as $seedBoxRecord)
        {
            $seedBox .= "<tr>";
            $seedBox .= sprintf('<td>%s</td>', $seedBoxRecord->id);
            $seedBox .= sprintf('<td>%s</td>', $seedBoxRecord->operator);
            $seedBox .= sprintf('<td>%s</td>', $seedBoxRecord->bandwidth ?: '');
            $seedBox .= sprintf('<td>%s</td>', $seedBoxRecord->ip ?: sprintf('%s ~ %s', $seedBoxRecord->ip_begin, $seedBoxRecord->ip_end));
            $seedBox .= sprintf('<td>%s</td>', $seedBoxRecord->comment);
            $seedBox .= sprintf('<td>%s</td>', $seedBoxRecord->statusText);
            $seedBox .= sprintf('<td><img style="cursor: pointer" class="staff_delete remove-seed-box-btn" src="pic/trans.gif" alt="D" title="%s" data-id="%s"></td>', $lang_functions['text_delete'], $seedBoxRecord->id);
            $seedBox .= "</tr>";
        }
        $seedBox .= '</table>';
    }
    $seedBox .= sprintf('<div><input type="button" id="add-seed-box-btn" value="%s"/></div>', $lang_usercp['add_seed_box_btn']);
    tr_small($lang_usercp['row_seed_box'], $seedBox, 1);
    $seedBoxCss = <<<CSS
.form-box {
padding: 15px;
}
.form-control-row {
display: flex;
align-items: center;
padding: 10px 0;
}
.form-control-row .label {
width: 80px
}
.form-control-row .field {

}
.form-control-row input,textarea {
width: 300px;
padding: 4px;
}
CSS;

    $seedBoxForm = <<<FORM
<div class="form-box">
<form id="seed-box-form">
    <div class="form-control-row">
        <div class="label">{$columnOperator}</div>
        <div class="field"><input type="text" name="params[operator]"></div>
    </div>
    <div class="form-control-row">
        <div class="label">{$columnBandwidth}</div>
        <div class="field"><input type="number" name="params[bandwidth]"></div>
    </div>
    <div class="form-control-row">
        <div class="label">{$columnIPBegin}</div>
        <div class="field"><input type="text" name="params[ip_begin]"></div>
    </div>
    <div class="form-control-row">
        <div class="label">{$columnIPEnd}</div>
        <div class="field"><input type="text" name="params[ip_end]"></div>
    </div>
    <div class="form-control-row">
        <div class="label">{$columnIP}</div>
        <div class="field"><input type="text" name="params[ip]"><div><small>{$columnIPHelp}</small></div></div>
    </div>
    <div class="form-control-row">
        <div class="label">{$columnComment}</div>
        <div class="field"><textarea name="params[comment]" rows="4"></textarea></div>
    </div>
</form>
</div>
FORM;
    $seedBoxJs = <<<JS
jQuery('#add-seed-box-btn').on('click', function () {
    layer.open({
        type: 1,
        title: "{$lang_usercp['row_seed_box']} {$lang_usercp['add_seed_box_btn']}",
        content: `$seedBoxForm`,
        btn: ['OK'],
        btnAlign: 'c',
        yes: function () {
            let params = jQuery('#seed-box-form').serialize()
            jQuery.post('ajax.php', params + "&action=addSeedBoxRecord", function (response) {
                console.log(response)
                if (response.ret != 0) {
                    layer.alert(response.msg)
                    return
                }
                window.location.reload()
            }, 'json')
        }
    })
});
jQuery('#seed-box-table').on('click', '.remove-seed-box-btn', function () {
    let params = {action: "removeSeedBoxRecord", params: {id: jQuery(this).attr("data-id")}}
    layer.confirm("{$lang_functions['std_confirm_remove']}", {btnAlign: 'c'}, function (index) {
        jQuery.post('ajax.php', params, function (response) {
            console.log(response)
            if (response.ret != 0) {
                layer.alert(response.msg)
                return
            }
            window.location.reload()
        }, 'json')
    })
});
JS;
    \Nexus\Nexus::js($seedBoxJs, 'footer', false);
    \Nexus\Nexus::css($seedBoxCss, 'footer', false);
}
//end seed box

if ($forumposts)
	tr($lang_usercp['row_forum_posts'], $forumposts." [<a href=\"userhistory.php?action=viewposts&id=".$CURUSER['id']."\" title=\"".$lang_usercp['link_view_posts']."\">".$lang_usercp['text_view']."</a>] (".$dayposts.$lang_usercp['text_posts_per_day']."; ".$percentages.$lang_usercp['text_of_total_posts'].")", 1);
?>
</table>
<table border="0" cellspacing="0" cellpadding="5" width=1200>
<?php
print("<td align=center class=tabletitle><b>".$lang_usercp['text_recently_read_topics']."</b></td>");
?>
</table>
<?php
print("<table border=0 cellspacing=0 cellpadding=3 width=1200><tr>".
"<td class=colhead align=left width=80%>".$lang_usercp['col_topic_title']."</td>".
"<td class=colhead align=center><nobr>".$lang_usercp['col_replies']."/".$lang_usercp['col_views']."</nobr></td>".
"<td class=colhead align=center>".$lang_usercp['col_topic_starter']."</td>".
"<td class=colhead align=center width=20%>".$lang_usercp['col_last_post']."</td>".
"</tr>");
$res_topics = sql_query("SELECT * FROM readposts INNER JOIN topics ON topics.id = readposts.topicid WHERE readposts.userid = ".$CURUSER['id']." ORDER BY readposts.id DESC LIMIT 5") or sqlerr();
while ($topicarr = mysql_fetch_assoc($res_topics))
{
	$topicid = $topicarr["id"];
	$topic_title = $topicarr["subject"];
	$topic_userid = $topicarr["userid"];
	$topic_views = $topicarr["views"];
	$views = number_format($topic_views);

	/// GETTING TOTAL NUMBER OF POSTS ///
	if (!$posts = $Cache->get_value('topic_'.$topicid.'_post_count')){
		$posts = get_row_count("posts","WHERE topicid=".sqlesc($topicid));
		$Cache->cache_value('topic_'.$topicid.'_post_count', $posts, 3600);
	}
	$replies = max(0, $posts - 1);

	/// GETTING USERID AND DATE OF LAST POST ///
	$arr = get_post_row($topicarr['lastpost']);
	$postid = intval($arr["id"] ?? 0);
	$userid = intval($arr["userid"] ?? 0);
	$added = gettime($arr['added'],true,false);

	/// GET NAME OF LAST POSTER ///
	$username = get_username($userid);

	/// GET NAME OF THE AUTHOR ///
	$author = get_username($topic_userid);
	$subject = "<a href=forums.php?action=viewtopic&topicid=$topicid><b>" . htmlspecialchars($topicarr["subject"]) . "</b></a>";

	print("<tr class=tableb><td style='padding-left: 10px' align=left class=rowfollow>$subject</td>".
	"<td align=center class=rowfollow>".$replies."/".$views."</td>" .
	"<td align=center class=rowfollow>".$author."</td>" .
	"<td align=center class=rowfollow><nobr>".$added." | ".$username."</nobr></td></tr>");
}
?>
  </table>
</td>
</tr>
<?php
stdfoot();
