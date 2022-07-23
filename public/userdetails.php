<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
parked();

function bark($msg)
{
	global $lang_userdetails;
	stdhead();
	stdmsg($lang_userdetails['std_error'], $msg);
	stdfoot();
	exit;
}

$id = intval($_GET["id"] ?? 0);
int_check($id,true);

if ($id != $CURUSER['id']){
	$r = sql_query("SELECT * FROM users WHERE id=".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
	$user = mysql_fetch_array($r) or bark($lang_userdetails['std_no_such_user']);
}
else
{
	$user = $CURUSER;
}
if ($user["status"] == "pending")
stderr($lang_userdetails['std_sorry'], $lang_userdetails['std_user_not_confirmed']);

$userInfo = \App\Models\User::query()->with(['valid_medals'])->findOrFail($user['id']);

if ($user['added'] == "0000-00-00 00:00:00" || $user['added'] == null)
$joindate = $lang_userdetails['text_not_available'];
else
$joindate = $user['added']." (" . gettime($user["added"], true, false, true).")";
$lastseen = $user["last_access"];
if ($lastseen == "0000-00-00 00:00:00" || $lastseen == null)
$lastseen = $lang_userdetails['text_not_available'];
else
{
	$lastseen .= " (" . gettime($lastseen, true, false, true).")";
}
$res = sql_query("SELECT COUNT(*) FROM comments WHERE user=" . $user['id']) or sqlerr();
$arr3 = mysql_fetch_row($res);
$torrentcomments = $arr3[0];
$res = sql_query("SELECT COUNT(*) FROM posts WHERE userid=" . $user['id']) or sqlerr();
$arr3 = mysql_fetch_row($res);
$forumposts = $arr3[0];

	$arr = get_country_row($user['country']);
	$country = "<img src=\"pic/flag/".$arr['flagpic']."\" alt=\"".$arr['name']."\" style='margin-left: 8pt' />";

	$arr = (array)get_downloadspeed_row($user['download']);
	$name = $arr['name'] ?? '';
	$download = "<img class=\"speed_down\" src=\"pic/trans.gif\" alt=\"Downstream Rate\" title=\"".$lang_userdetails['title_download'].$name."\" /> ".$name;

	$arr = (array)get_uploadspeed_row($user['upload']);
    $name = $arr['name'] ?? '';
	$upload = "<img class=\"speed_up\" src=\"pic/trans.gif\" alt=\"Upstream Rate\" title=\"".$lang_userdetails['title_upload'].$name."\" /> ".$name;

	$arr = get_isp_row($user['isp']);
    $name = $arr['name'] ?? '';
	$isp = $name;

if ($user["gender"] == "Male")
$gender = "<img class='male' src='pic/trans.gif' alt='Male' title='".$lang_userdetails['title_male']."' style='margin-left: 4pt' />";
elseif ($user["gender"] == "Female")
$gender = "<img class='female' src='pic/trans.gif' alt='Female' title='".$lang_userdetails['title_female']."' style='margin-left: 4pt' />";
elseif ($user["gender"] == "N/A")
$gender = "<img class='no_gender' src='pic/trans.gif' alt='N/A' title='".$lang_userdetails['title_not_available']."' style='margin-left: 4pt' />";

stdhead($lang_userdetails['head_details_for']. $user["username"]);
$enabled = $user["enabled"] == 'yes';
$moviepicker = $user["picker"] == 'yes';

print("<h1 style='margin:0px'>" . get_username($user['id'], true,false) . $country."</h1>");

if (!$enabled)
print("<p><b>".$lang_userdetails['text_account_disabled_note']."</b></p>");
elseif ($CURUSER["id"] <> $user["id"])
{
	$r = sql_query("SELECT id FROM friends WHERE userid={$CURUSER['id']} AND friendid=$id") or sqlerr(__FILE__, __LINE__);
	$friend = mysql_num_rows($r);
	$r = sql_query("SELECT id FROM blocks WHERE userid={$CURUSER['id']} AND blockid=$id") or sqlerr(__FILE__, __LINE__);
	$block = mysql_num_rows($r);

	if ($friend)
	print("<p>(<a href=\"friends.php?action=delete&amp;type=friend&amp;targetid=".$id."\">".$lang_userdetails['text_remove_from_friends']."</a>)</p>\n");
	elseif($block)
	print("<p>(<a href=\"friends.php?action=delete&amp;type=block&amp;targetid=".$id."\">".$lang_userdetails['text_remove_from_blocks']."</a>)</p>\n");
	else
	{
		print("<p>(<a href=\"friends.php?action=add&amp;type=friend&amp;targetid=".$id."\">".$lang_userdetails['text_add_to_friends']."</a>)");
		print(" - (<a href=\"friends.php?action=add&amp;type=block&amp;targetid=".$id."\">".$lang_userdetails['text_add_to_blocks']."</a>)</p>");
	}
}
begin_main_frame();
if ($CURUSER['id'] == $user['id'] || get_user_class() >= $cruprfmanage_class)
	print("<h2>".$lang_userdetails['text_flush_ghost_torrents']."<a class=\"altlink\" href=\"takeflush.php?id=".$id."\">".$lang_userdetails['text_here']."</a></h2>\n");
?>
<table width="100%" border="1" cellspacing="0" cellpadding="5">
<?php
if (($user["privacy"] != "strong") OR (get_user_class() >= $prfmanage_class) || $CURUSER['id'] == $user['id']){
//Xia Zuojie: Taste compatibility is extremely slow. It can takes thounsands of datebase queries. It is disabled until someone makes it fast.
/*
	if (isset($CURUSER) && $CURUSER['id'] != $user['id'])
	{
		$user_snatched = sql_query("SELECT * FROM snatched WHERE userid = $CURUSER['id']") or sqlerr(__FILE__, __LINE__);
		if(mysql_num_rows($user_snatched) == 0)
		$compatibility_info = $lang_userdetails['text_unknown'];
		else
		{
			while ($user_snatched_arr = mysql_fetch_array($user_snatched))
			{
				$torrent_2_user_value = get_torrent_2_user_value($user_snatched_arr);

				$user_snatched_res_target = sql_query("SELECT * FROM snatched WHERE torrentid = " . $user_snatched_arr['torrentid'] . " AND userid = " . $user['id']) or sqlerr(__FILE__, __LINE__);	//
				if(mysql_num_rows($user_snatched_res_target) == 1)	// have other peole snatched this torrent
				{
					$user_snatched_arr_target = mysql_fetch_array($user_snatched_res_target) or sqlerr(__FILE__, __LINE__);	// find target user's current analyzing torrent's snatch info
					$torrent_2_user_value_target = get_torrent_2_user_value($user_snatched_arr_target);	//get this torrent to target user's value

					if(!isset($other_user_2_curuser_value[$user_snatched_arr_target['userid']]))	// first, set to 0
					$other_user_2_curuser_value[$user_snatched_arr_target['userid']] = 0.0;

					$other_user_2_curuser_value[$user_snatched_arr_target['userid']] += $torrent_2_user_value_target * $torrent_2_user_value;
				}
			}

			$val = $other_user_2_curuser_value[$user['id']];
			if ($val > 1)
			{
				$val = 1;
				$compatibility_info = $lang_userdetails['text_super'];
				$bar_url = "pic/loadbargreen.gif";
			}
			elseif ($val > 0.7 && $val<=1)
			{
				$compatibility_info = $lang_userdetails['text_very_high'];
				$bar_url = "pic/loadbargreen.gif";
			}
			elseif ($val > 0.45 && $val<=0.7)
			{
				$compatibility_info = $lang_userdetails['text_high'];
				$bar_url = "pic/loadbargreen.gif";
			}
			elseif ($val > 0.2 && $val<=0.45)
			{
				$compatibility_info = $lang_userdetails['text_medium'];
				$bar_url = "pic/loadbaryellow.gif";
			}
			elseif ($val > 0.05 && $val<=0.2)
			{
				$compatibility_info = $lang_userdetails['text_low'];
				$bar_url = "pic/loadbarred.gif";
			}
			else
			{
				$val = 0;
				$compatibility_info = $lang_userdetails['text_very_low'];
				$bar_url = "pic/loadbarred.gif";
			}
			$width = $val * 400;
			$compatibility_info = "<table align=left border=0 width=400><tr><td style='padding: 0px; background-image: url(pic/loadbarbg.gif); background-repeat: repeat-x; width: 400px' title='" . number_format($val * 100, 2) . "%'><img align=left height=15 width=" . $width . " src=\"" . $bar_url ."\" alt='" . number_format($val * 100, 2) . "%'></td><td align=right class=embedded><strong>&nbsp;&nbsp;&nbsp;<nobr>" . $compatibility_info . "</nobr> </strong></td></tr></table>";

			//die("ss" . htmlspecialchars($compatibility_info));
		}
		print("<tr><td class=rowhead width=13%>".$lang_userdetails['row_compatibility']."</td><td class=rowfollow align=left width=87%>". $compatibility_info ."</td></tr>\n");
	}
*/
    tr_small($lang_userdetails['text_user_id'], $user['id'], 1);
	if ($CURUSER['id'] == $user['id'] || get_user_class() >= $viewinvite_class){
	if ($user["invites"] <= 0)
	tr_small($lang_userdetails['row_invitation'], $lang_userdetails['text_no_invitation'], 1);
	else
	tr_small($lang_userdetails['row_invitation'], "<a href=\"invite.php?id=".$user['id']."\" title=\"".$lang_userdetails['link_send_invitation']."\">".$user['invites']."</a>", 1);}
	else{
	if ($CURUSER['id'] != $user['id'] || get_user_class() != $viewinvite_class){
	if ($user["invites"] <= 0)
	tr_small($lang_userdetails['row_invitation'], $lang_userdetails['text_no_invitation'], 1);
	else
	tr($lang_userdetails['row_invitation'], $user['invites'], 1);}
	}
	if ($user["invited_by"] > 0) {
		tr_small($lang_userdetails['row_invited_by'], get_username($user['invited_by']), 1);
	}
	tr_small($lang_userdetails['row_join_date'], $joindate, 1);
	tr_small($lang_userdetails['row_last_seen'], $lastseen, 1);
if ($where_tweak == "yes") {
	tr_small($lang_userdetails['row_last_seen_location'], $user['page'], 1);
}
if (get_user_class() >= $userprofile_class OR $user["privacy"] == "low") {
	tr_small($lang_userdetails['row_email'], "<a href=\"mailto:".$user['email']."\">".$user['email']."</a>", 1);
}
if (get_user_class() >= $userprofile_class) {
	$resip = sql_query("SELECT ip FROM iplog WHERE userid =$id GROUP BY ip") or sqlerr(__FILE__, __LINE__);
	$iphistory = mysql_num_rows($resip);

	if ($iphistory > 0)
	tr_small($lang_userdetails['row_ip_history'], $lang_userdetails['text_user_earlier_used']."<b><a href=\"iphistory.php?id=" . $user['id'] . "\">" . $iphistory. $lang_userdetails['text_different_ips'].add_s($iphistory, true)."</a></b>", 1);

}
$seedBoxRep = new \App\Repositories\SeedBoxRepository();
if (get_user_class() >= $userprofile_class ||  $user["id"] == $CURUSER["id"])
{
    $seedBoxIcon = $seedBoxRep->renderIcon($CURUSER['ip'], $CURUSER['id']);
	if ($enablelocation_tweak == 'yes'){
		list($loc_pub, $loc_mod) = get_ip_location($user['ip']);
		$locationinfo = "<span title=\"" . $loc_mod . "\">[" . $loc_pub . "]</span>";
	}
	else $locationinfo = "";
	tr_small($lang_userdetails['row_ip_address'], $user['ip'].$locationinfo.$seedBoxIcon, 1);
}
$clientselect = '';
$res = sql_query("SELECT peer_id, agent, ipv4, ipv6, port FROM peers WHERE userid = {$user['id']} GROUP BY agent") or sqlerr();
if (mysql_num_rows($res) > 0)
{
    $clientselect .= "<table border='1' cellspacing='0' cellpadding='5'><tr><td class='colhead'>Agent</td><td class='colhead'>IPV4</td><td class='colhead'>IPV6</td><td class='colhead'>Port</td></tr>";
	while($arr = mysql_fetch_assoc($res))
	{
	    $clientselect .= "<tr>";
		$clientselect .= sprintf('<td>%s</td>', get_agent($arr['peer_id'], $arr['agent']));
		if (get_user_class() >= $userprofile_class ||  $user["id"] == $CURUSER["id"]) {
            $clientselect .= sprintf('<td>%s</td><td>%s</td><td>%s</td>', $arr['ipv4'].$seedBoxRep->renderIcon($arr['ipv4'], $CURUSER['id']), $arr['ipv6'].$seedBoxRep->renderIcon($arr['ipv6'], $CURUSER['id']), $arr['port']);
        } else {
            $clientselect .= sprintf('<td>%s</td><td>%s</td><td>%s</td>', '---', '---', '---');
        }
        $clientselect .= "</tr>";
	}
	$clientselect .= '</table>';
}
if ($clientselect)
	tr_small($lang_userdetails['row_bt_client'], $clientselect, 1);


//真实分享、上传、下载率显示
$rs_true_trans = sql_query("SELECT SUM(uploaded), SUM(downloaded) FROM snatched WHERE userid = $user[id]") or sqlerr(__FILE__, __LINE__);
$true_download = 0;
$true_upload = 0;
if(mysql_num_rows($rs_true_trans) > 0)
{
    $row_true_trans = mysql_fetch_assoc($rs_true_trans);
    $true_upload = $row_true_trans['SUM(uploaded)'];
    $true_download = $row_true_trans['SUM(downloaded)'];

}
if ($user["downloaded"] > 0 && $true_download > 0)
{
	$sr = floor($user["uploaded"] / $user["downloaded"] * 1000) / 1000;
	$true_ratio = floor($true_upload / $true_download * 1000) / 1000;
	$sr = "<tr><td class=\"embedded\"><strong>" . $lang_userdetails['row_share_ratio'] . "</strong>:  <font color=\"" . get_ratio_color($sr) . "\">" . number_format($sr, 3) . "</font>（<strong>".$lang_userdetails['row_real_share_ratio']."</strong>：".number_format($true_ratio, 3)."）</td><td class=\"embedded\">&nbsp;&nbsp;" . get_ratio_img($sr) . "</td></tr>";

}
//end

$xfer = "<tr><td class=\"embedded\"><strong>" . $lang_userdetails['row_uploaded'] . "</strong>:  ". mksize($user["uploaded"]) . "</td><td class=\"embedded\">&nbsp;&nbsp;<strong>" . $lang_userdetails['row_downloaded'] . "</strong>:  " . mksize($user["downloaded"]) . "</td></tr>";
$true_xfer = "<tr><td class=\"embedded\"><strong>" . $lang_userdetails['row_real_uploaded'] . "</strong>:  ". mksize($true_upload) . "</td><td class=\"embedded\">&nbsp;&nbsp;<strong>" . $lang_userdetails['row_real_downloaded'] . "</strong>:  " . mksize($true_download) . "</td><td class=\"embedded\" style=\"color: #7d7b7b;\">&nbsp;&nbsp;" . $lang_userdetails['row_real_ps'] . "</td></tr>";
tr_small($lang_userdetails['row_transfer'], "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">" . ($sr ?? '') . $xfer .  $true_xfer . "</table>", 1);


if ($user["leechtime"] > 0)
{
	$slr = floor($user["seedtime"] / $user["leechtime"] * 1000) / 1000;
	$slr = "<tr><td class=\"embedded\"><strong>" . $lang_userdetails['text_seeding_leeching_time_ratio'] . "</strong>:  <font color=\"" . get_ratio_color($slr) . "\">" . number_format($slr, 3) . "</font></td><td class=\"embedded\">&nbsp;&nbsp;" . get_ratio_img($slr) . "</td></tr>";
}

$slt = "<tr><td class=\"embedded\"><strong>" . $lang_userdetails['text_seeding_time'] . "</strong>:  ". mkprettytime($user["seedtime"]) . "</td><td class=\"embedded\">&nbsp;&nbsp;<strong>" . $lang_userdetails['text_leeching_time'] . "</strong>:  " . mkprettytime($user["leechtime"]) . "</td></tr>";

	tr_small($lang_userdetails['row_sltime'], "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">" . ($slr ?? '') . $slt . "</table>", 1);

if ($user["download"] && $user["upload"])
tr_small($lang_userdetails['row_internet_speed'], $download."&nbsp;&nbsp;&nbsp;&nbsp;".$upload."&nbsp;&nbsp;&nbsp;&nbsp;".$isp, 1);
tr_small($lang_userdetails['row_gender'], $gender, 1);

if (($user['donated'] > 0 || $user['donated_cny'] > 0 )&& (get_user_class() >= $userprofile_class || $CURUSER["id"] == $user["id"]))
tr_small($lang_userdetails['row_donated'], "$".htmlspecialchars($user['donated'])."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".htmlspecialchars($user['donated_cny']), 1);

if ($user["avatar"])
tr_small($lang_userdetails['row_avatar'], return_avatar_image(htmlspecialchars(trim($user["avatar"]))), 1);

if ($userInfo->valid_medals->isNotEmpty()) {
    tr_small($lang_userdetails['row_medal'], build_medal_image($userInfo->valid_medals, 200, $CURUSER['id'] == $user['id']), 1);
    $warnMedalJs = <<<JS
jQuery('input[type="checkbox"][name="medal_wearing_status"]').on("change", function (e) {
    let input = jQuery(this);
    let checked = input.prop("checked")
    jQuery.post('ajax.php', {params: {id: this.value}, action: 'toggleUserMedalStatus'}, function (response) {
        console.log(response)
        if (response.ret != 0) {
            input.prop("checked", !checked)
            alert(response.msg)
        }
    }, 'json')
})
JS;
    \Nexus\Nexus::js($warnMedalJs, 'footer', false);
}

$uclass = get_user_class_image($user["class"]);
$utitle = get_user_class_name($user["class"],false,false,true);
$uclassImg = "<img alt=\"".get_user_class_name($user["class"],false,false,true)."\" title=\"".get_user_class_name($user["class"],false,false,true)."\" src=\"".$uclass."\" /> ".($user['title']!=="" ? "&nbsp;".htmlspecialchars(trim($user["title"]))."" :  "");
if ($user['class'] == UC_VIP && !empty($user['vip_until']) && strtotime($user['vip_until'])) {
    $uclassImg .= sprintf('%s: %s', $lang_userdetails['row_vip_until'], $user['vip_until']);
}
tr_small($lang_userdetails['row_class'], $uclassImg, 1);

tr_small($lang_userdetails['row_torrent_comment'], ($torrentcomments && ($user["id"] == $CURUSER["id"] || get_user_class() >= $viewhistory_class) ? "<a href=\"userhistory.php?action=viewcomments&amp;id=".$id."\" title=\"".$lang_userdetails['link_view_comments']."\">".$torrentcomments."</a>" : $torrentcomments), 1);

tr_small($lang_userdetails['row_forum_posts'], ($forumposts && ($user["id"] == $CURUSER["id"] || get_user_class() >= $viewhistory_class) ? "<a href=\"userhistory.php?action=viewposts&amp;id=".$id."\" title=\"".$lang_userdetails['link_view_posts']."\">".$forumposts."</a>" : $forumposts), 1);

if ($user["id"] == $CURUSER["id"] || get_user_class() >= $viewhistory_class) {
    if (\App\Models\HitAndRun::getIsEnabled()) {
        $hrStatus = (new \App\Repositories\HitAndRunRepository())->getStatusStats($user['id']);
        tr_small('H&R', sprintf('<a href="myhr.php?userid=%s" target="_blank">%s</a>', $user['id'], $hrStatus), 1);
    }
    if (\App\Models\Claim::getConfigIsEnabled()) {
        $states = (new \App\Repositories\ClaimRepository())->getStats($user['id']);
        tr_small($lang_functions['menu_claim'], sprintf('<a href="claim.php?uid=%s" target="_blank">%s</a>', $user['id'], $states), 1);
    }
    tr_small($lang_userdetails['row_karma_points'], number_format($user['seedbonus'], 1), 1);
    tr_small($lang_functions['text_seed_points'], number_format($user['seed_points'], 1), 1);
}


if ($user["ip"] && (get_user_class() >= $torrenthistory_class || $user["id"] == $CURUSER["id"])){

tr_small($lang_userdetails['row_uploaded_torrents'], "<a href=\"javascript: getusertorrentlistajax('".$user['id']."', 'uploaded', 'ka'); klappe_news('a')\"><img class=\"plus\" src=\"pic/trans.gif\" id=\"pica\" alt=\"Show/Hide\" title=\"".$lang_userdetails['title_show_or_hide'] ."\" />   <u>".$lang_userdetails['text_show_or_hide']."</u></a><div id=\"ka\" style=\"display: none;\"></div>", 1);


tr_small($lang_userdetails['row_current_seeding'], "<a href=\"javascript: getusertorrentlistajax('".$user['id']."', 'seeding', 'ka1'); klappe_news('a1')\"><img class=\"plus\" src=\"pic/trans.gif\" id=\"pica1\" alt=\"Show/Hide\" title=\"".$lang_userdetails['title_show_or_hide']."\" />   <u>".$lang_userdetails['text_show_or_hide']."</u></a><div id=\"ka1\" style=\"display: none;\"></div>", 1);


tr_small($lang_userdetails['row_current_leeching'], "<a href=\"javascript: getusertorrentlistajax('".$user['id']."', 'leeching', 'ka2'); klappe_news('a2')\"><img class=\"plus\" src=\"pic/trans.gif\" id=\"pica2\" alt=\"Show/Hide\" title=\"".$lang_userdetails['title_show_or_hide']."\" />   <u>".$lang_userdetails['text_show_or_hide']."</u></a><div id=\"ka2\" style=\"display: none;\"></div>", 1);


tr_small($lang_userdetails['row_completed_torrents'], "<a href=\"javascript: getusertorrentlistajax('".$user['id']."', 'completed', 'ka3'); klappe_news('a3')\"><img class=\"plus\" src=\"pic/trans.gif\" id=\"pica3\" alt=\"Show/Hide\" title=\"".$lang_userdetails['title_show_or_hide']."\" />   <u>".$lang_userdetails['text_show_or_hide']."</u></a><div id=\"ka3\" style=\"display: none;\"></div>", 1);


tr_small($lang_userdetails['row_incomplete_torrents'], "<a href=\"javascript: getusertorrentlistajax('".$user['id']."', 'incomplete', 'ka4'); klappe_news('a4')\"><img class=\"plus\" src=\"pic/trans.gif\" id=\"pica4\" alt=\"Show/Hide\" title=\"".$lang_userdetails['title_show_or_hide']."\" />   <u>".$lang_userdetails['text_show_or_hide']."</u></a><div id=\"ka4\" style=\"display: none;\"></div>", 1);
}
if ($user["info"])
	print("<tr><td align=\"left\" colspan=\"2\" class=\"text\">" . format_comment($user["info"],false) . "</td></tr>\n");
}
else
{
	print("<tr><td align=\"left\" colspan=\"2\" class=\"text\"><font color=\"blue\">".$lang_userdetails['text_public_access_denied'].$user['username'].$lang_userdetails['text_user_wants_privacy']."</font></td></tr>\n");
}
$showpmbutton = 0;
if ($CURUSER["id"] != $user["id"])
if (get_user_class() >= $staffmem_class)
$showpmbutton = 1;
elseif ($user["acceptpms"] == "yes")
{
	$r = sql_query("SELECT id FROM blocks WHERE userid={$user['id']} AND blockid={$CURUSER['id']}") or sqlerr(__FILE__,__LINE__);
	$showpmbutton = (mysql_num_rows($r) == 1 ? 0 : 1);
}
elseif ($user["acceptpms"] == "friends")
{
	$r = sql_query("SELECT id FROM friends WHERE userid={$user['id']} AND friendid={$CURUSER['id']}") or sqlerr(__FILE__,__LINE__);
	$showpmbutton = (mysql_num_rows($r) == 1 ? 1 : 0);
}
if ($CURUSER["id"] != $user["id"]){
print("<tr><td colspan=\"2\" align=\"center\">");
if ($showpmbutton)
print("<a href=\"sendmessage.php?receiver=".htmlspecialchars($user['id'])."\"><img class=\"f_pm\" src=\"pic/trans.gif\" alt=\"PM\" title=\"".$lang_userdetails['title_send_pm']."\" /></a>");

print("<a href=\"report.php?user=".htmlspecialchars($user['id'])."\"><img class=\"f_report\" src=\"pic/trans.gif\" alt=\"Report\" title=\"".$lang_userdetails['title_report_user']."\" /></a>");
print("</td></tr>");
}
print("</table>\n");

if (get_user_class() >= $prfmanage_class && $user["class"] < get_user_class())
{
	begin_frame($lang_userdetails['text_edit_user'], true);
	print("<form method=\"post\" action=\"modtask.php\">");
	print("<input type=\"hidden\" name=\"action\" value=\"edituser\" />");
	print("<input type=\"hidden\" name=\"userid\" value=\"".$id."\" />");
	print("<input type=\"hidden\" name=\"returnto\" value=\"".htmlspecialchars("userdetails.php?id=$id")."\" />");
	print("<table width=\"100%\" class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n");
	tr($lang_userdetails['row_title'], "<input type=\"text\" size=\"60\" name=\"title\" value=\"" . htmlspecialchars(trim($user['title'])) . "\" />", 1);
	$avatar = htmlspecialchars(trim($user["avatar"]));

	tr($lang_userdetails['row_privacy_level'], "<input type=\"radio\" name=\"privacy\" value=\"low\"".($user["privacy"] == "low" ? " checked=\"checked\"" : "")." />".$lang_userdetails['radio_low']."<input type=\"radio\" name=\"privacy\" value=\"normal\"".($user["privacy"] == "normal" ? " checked=\"checked\"" : "")." />".$lang_userdetails['radio_normal']."<input type=\"radio\" name=\"privacy\" value=\"strong\"".($user["privacy"] == "strong" ? " checked=\"checked\"" : "")." />".$lang_userdetails['radio_strong'], 1);
	tr($lang_userdetails['row_avatar_url'], "<input type=\"text\" size=\"60\" name=\"avatar\" value=\"".$avatar."\" />", 1);
	$signature = trim($user["signature"]);
	tr($lang_userdetails['row_signature'], "<textarea cols=\"60\" rows=\"6\" name=\"signature\">".$signature."</textarea>", 1);

	if (get_user_class() == UC_STAFFLEADER)
	{
		tr($lang_userdetails['row_donor_status'], "<input type=\"radio\" name=\"donor\" value=\"yes\"" .($user["donor"] == "yes" ? " checked=\"checked\"" : "")." />".$lang_userdetails['radio_yes']." <input type=\"radio\" name=\"donor\" value=\"no\"" .($user["donor"] == "no" ? " checked=\"checked\"" : "").">".$lang_userdetails['radio_no'], 1);
		tr($lang_userdetails['row_donated'], "USD: <input type=\"text\" size=\"5\" name=\"donated\" value=\"" . htmlspecialchars($user['donated']) . "\" />&nbsp;&nbsp;&nbsp;&nbsp;CNY: <input type=\"text\" size=\"5\" name=\"donated_cny\" value=\"" . htmlspecialchars($user['donated_cny']) . "\" />" . $lang_userdetails['text_transaction_memo'] . "<input type=\"text\" size=\"50\" name=\"donation_memo\" />", 1);
        tr($lang_userdetails['row_donoruntil'], "<input type=\"text\" name=\"donoruntil\" value=\"".htmlspecialchars($user["donoruntil"])."\" /> ".$lang_userdetails['text_donoruntil_note'], 1);
	}
	if (get_user_class() == $prfmanage_class)
		$maxclass = UC_VIP;
	else
		$maxclass = get_user_class() - 1;
	$classselect=classlist('class', $maxclass, $user["class"]);
	tr($lang_userdetails['row_class'], $classselect, 1);
	tr($lang_userdetails['row_vip_by_bonus'], "<input type=\"radio\" name=\"vip_added\" value=\"yes\"" .($user["vip_added"] == "yes" ? " checked=\"checked\"" : "")." />".$lang_userdetails['radio_yes']." <input type=\"radio\" name=\"vip_added\" value=\"no\"" .($user["vip_added"] == "no" ? " checked=\"checked\"" : "")." />".$lang_userdetails['radio_no']."<br />".$lang_userdetails['text_vip_by_bonus_note'], 1);
	tr($lang_userdetails['row_vip_until'], "<input type=\"text\" name=\"vip_until\" value=\"".htmlspecialchars($user["vip_until"])."\" /> ".$lang_userdetails['text_vip_until_note'], 1);
	$supportlang = htmlspecialchars($user["supportlang"]);
	$supportfor = htmlspecialchars($user["supportfor"]);
	$pickfor = htmlspecialchars($user["pickfor"]);
	$staffduties = htmlspecialchars($user["stafffor"]);

	tr($lang_userdetails['row_staff_duties'], "<textarea cols=\"60\" rows=\"6\" name=\"staffduties\">".$staffduties."</textarea>", 1);
	tr($lang_userdetails['row_support_language'], "<input type=\"text\" name=\"supportlang\" value=\"".$supportlang."\" />", 1);
	tr($lang_userdetails['row_support'], "<input type=\"radio\" name=\"support\" value=\"yes\"" .($user["support"] == "yes" ? " checked=\"checked\"" : "")." />".$lang_userdetails['radio_yes']." <input type=\"radio\" name=\"support\" value=\"no\"" .($user["support"] == "no" ? " checked=\"checked\"" : "")." />".$lang_userdetails['radio_no'], 1);
	tr($lang_userdetails['row_support_for'], "<textarea cols=\"60\" rows=\"6\" name=\"supportfor\">".$supportfor."</textarea>", 1);

	tr($lang_userdetails['row_movie_picker'], "<input name=\"moviepicker\" value=\"yes\" type=\"radio\"" . ($moviepicker ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_yes']."<input name=\"moviepicker\" value=\"no\" type=\"radio\"" . (!$moviepicker ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_no'], 1);
	tr($lang_userdetails['row_pick_for'], "<textarea cols=\"60\" rows=\"6\" name=\"pickfor\">".$pickfor."</textarea>", 1);

	if (get_user_class() >= $cruprfmanage_class)
	{
		$modcomment = htmlspecialchars($user["modcomment"]);
		tr($lang_userdetails['row_comment'], "<textarea cols=\"60\" rows=\"6\" name=\"modcomment\">".$modcomment."</textarea>", 1);
		$bonuscomment = htmlspecialchars($user["bonuscomment"]);
		tr($lang_userdetails['row_seeding_karma'], "<textarea cols=\"60\" rows=\"6\" name=\"bonuscomment\" readonly=\"readonly\">".$bonuscomment."</textarea>", 1);
	}
	$warned = $user["warned"] == "yes";

	print("<tr><td class=\"rowhead\">".$lang_userdetails['row_warning_system']."</td><td class=\"rowfollow\" align=\"left\" ><table class=\"main\" cellspacing=\"0\" cellpadding=\"5\"><tr><td class=\"rowfollow\">" . ($warned ? "<input name=\"warned\" value=\"yes\" type=\"radio\" checked=\"checked\" />".$lang_userdetails['radio_yes']."<input name=\"warned\" value=\"no\" type=\"radio\" />".$lang_userdetails['radio_no'] : $lang_userdetails['text_not_warned'] ) ."</td>");

	if ($warned)
	{
		$warneduntil = $user['warneduntil'];
		if ($warneduntil == '0000-00-00 00:00:00' || $warneduntil == null)
		print("<td align=\"center\" class=\"rowfollow\">".$lang_userdetails['text_arbitrary_duration']."</td>\n");
		else
		{
			print("<td align=\"left\" class=\"rowfollow\">".$lang_userdetails['text_until'].$warneduntil);
			print("<br />(" . mkprettytime(strtotime($warneduntil) - strtotime(date("Y-m-d H:i:s"))) .$lang_userdetails['text_to_go'] .")</td>\n");
		}
		print("</tr>");

	}else{

		print("<td align=\"left\" class=\"rowfollow\">".$lang_userdetails['text_warn_for']."<select name=\"warnlength\">\n");
		print("<option value=\"0\">------</option>\n");
		print("<option value=\"1\">1 ".$lang_userdetails['text_week']."</option>\n");
		print("<option value=\"2\">2 ".$lang_userdetails['text_weeks']."</option>\n");
		print("<option value=\"4\">4 ".$lang_userdetails['text_weeks']."</option>\n");
		print("<option value=\"8\">8 ".$lang_userdetails['text_weeks']."</option>\n");
		print("<option value=\"255\">".$lang_userdetails['text_unlimited']."</option>\n");
		print("</select></td></tr>\n");
		print("<tr><td align=\"left\" class=\"rowfollow\">".$lang_userdetails['text_reason_of_warning']."</td><td align=\"left\" class=\"rowfollow\"><input type=\"text\" size=\"60\" name=\"warnpm\" /></td></tr>");
	}


	$elapsedlw = get_elapsed_time(strtotime($user["lastwarned"]));
	print("<tr><td align=\"left\" class=\"rowfollow\">".$lang_userdetails['text_times_warned']."</td><td align=\"left\" class=\"rowfollow\">".$user['timeswarned']."</td></tr>\n");

	if ($user["timeswarned"] == 0)
	{
		print("<tr><td align=\"left\" class=\"rowfollow\">".$lang_userdetails['text_last_warning']."</td><td align=\"left\" class=\"rowfollow\">".$lang_userdetails['text_not_warned_note']."</td></tr>\n");
	}else{
		if ($user["warnedby"] != "System")
		{
			$res = sql_query("SELECT id, username, warnedby FROM users WHERE id = " . $user['warnedby']) or sqlerr(__FILE__,__LINE__);
			$arr = mysql_fetch_assoc($res);
			$warnedby = "<br />[".$lang_userdetails['text_by']."<u>" . get_username($arr['id']) . "</u></a>]";
		}else{
			$warnedby = "<br />[".$lang_userdetails['text_by_system']."]";
			print("<tr><td class=\"rowfollow\">".$lang_userdetails['text_last_warning']."</td><td align=\"left\" class=\"rowfollow\"> {$user['lastwarned']} .(".$lang_userdetails['text_until'] ."$elapsedlw)   $warnedby</td></tr>\n");
		}
		print("<tr><td class=\"rowfollow\">".$lang_userdetails['text_last_warning']."</td><td align=\"left\" class=\"rowfollow\"> {$user['lastwarned']} ($elapsedlw".$lang_userdetails['text_ago'].")   ".$warnedby."</td></tr>\n");
	}

	$leechwarn = $user["leechwarn"] == "yes";
	print("<tr><td class=\"rowfollow\">".$lang_userdetails['row_auto_warning']."<br /><i>(".$lang_userdetails['text_low_ratio'].")</i></td>");

	if ($leechwarn)
	{
		print("<td align=\"left\" class=\"rowfollow\"><font color=\"red\">".$lang_userdetails['text_leech_warned']."</font> ");
		$leechwarnuntil = $user['leechwarnuntil'];
		if ($leechwarnuntil != '0000-00-00 00:00:00' || $leechwarnuntil != null)
		{
			print($lang_userdetails['text_until'].$leechwarnuntil);
			print("<br />(" . mkprettytime(strtotime($leechwarnuntil) - strtotime(date("Y-m-d H:i:s"))) .$lang_userdetails['text_to_go'].")");
			printf('&nbsp;<input id="remove-leech-warn" type="button" class="btn" value="Remove" data-uid="%s" />', $user['id']);
			$removeLeechWarnJs = <<<JS
jQuery('#remove-leech-warn').on('click', function () {
    if (!window.confirm('{$lang_userdetails['sure_to_remove_leech_warn']}')) {
        return
    }
    let params = {action: 'removeUserLeechWarn', params: {uid: jQuery(this).attr('data-uid')}}
    jQuery.post('ajax.php', params, function (response) {
        console.log(response)
        if (response.ret == 0) {
            location.reload()
        } else {
            alert(response.msg)
        }
    }, 'json')
})
JS;
            \Nexus\Nexus::js($removeLeechWarnJs, 'footer', false);
		}else{
			print("<i>".$lang_userdetails['text_for_unlimited_time']."</i>");
		}
		print("</td></tr>");
	}else{
		print("<td class=\"rowfollow\">".$lang_userdetails['text_no_warned']."</td></tr>\n");
	}
	print("</table></td></tr>");
	tr($lang_userdetails['row_enabled'], "<input name=\"enabled\" value=\"yes\" type=\"radio\"" . ($enabled ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_yes']."<input name=\"enabled\" value=\"no\" type=\"radio\"" . (!$enabled ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_no'], 1);
//	tr($lang_userdetails['row_enabled'], $lang_userdetails['disable_user_migrated'], 1);
	tr($lang_userdetails['row_forum_post_possible'], "<input type=\"radio\" name=\"forumpost\" value=\"yes\"" .($user["forumpost"]=="yes" ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_yes']."<input type=\"radio\" name=\"forumpost\" value=\"no\"" .($user["forumpost"]=="no" ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_no'], 1);
	tr($lang_userdetails['row_upload_possible'], "<input type=\"radio\" name=\"uploadpos\" value=\"yes\"" .($user["uploadpos"]=="yes" ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_yes']."<input type=\"radio\" name=\"uploadpos\" value=\"no\"" .($user["uploadpos"]=="no" ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_no'], 1);
	tr($lang_userdetails['row_download_possible'], "<input type=\"radio\" name=\"downloadpos\" value=\"yes\"" .($user["downloadpos"]=="yes" ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_yes']."<input type=\"radio\" name=\"downloadpos\" value=\"no\"" .($user["downloadpos"]=="no" ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_no'], 1);
	tr($lang_userdetails['row_show_ad'], "<input type=\"radio\" name=\"noad\" value=\"no\"" .($user["noad"]=="no" ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_yes']."<input type=\"radio\" name=\"noad\" value=\"yes\"" .($user["noad"]=="yes" ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_no'], 1);
	tr($lang_userdetails['row_no_ad_until'], "<input type=\"text\" name=\"noaduntil\" value=\"".htmlspecialchars($user["noaduntil"])."\" /> ".$lang_userdetails['text_no_ad_until_note'], 1);
	if (get_user_class() >= $cruprfmanage_class)
	{
		tr($lang_userdetails['row_change_username'], "<input type=\"text\" size=\"25\" name=\"username\" value=\"" . htmlspecialchars($user['username']) . "\" />", 1);

		tr($lang_userdetails['row_change_email'], "<input type=\"text\" size=\"80\" name=\"email\" value=\"" . htmlspecialchars($user['email']) . "\" />", 1);
	}

	tr($lang_userdetails['row_change_password'], "<input type=\"password\" name=\"chpassword\" size=\"50\" />", 1);
	tr($lang_userdetails['row_repeat_password'], "<input type=\"password\" name=\"passagain\" size=\"50\" />", 1);

	if (get_user_class() >= $cruprfmanage_class)
	{
//		tr($lang_userdetails['row_amount_uploaded'], "<input disabled type=\"text\" size=\"60\" name=\"uploaded\" value=\"" . htmlspecialchars($user['uploaded']) . "\" /><input type=\"hidden\" name=\"ori_uploaded\" value=\"" . htmlspecialchars($user['uploaded']) . "\" />".$lang_userdetails['change_field_value_migrated'], 1);
//		tr($lang_userdetails['row_amount_downloaded'], "<input disabled type=\"text\" size=\"60\" name=\"downloaded\" value=\"" .htmlspecialchars($user['downloaded']) . "\" /><input type=\"hidden\" name=\"ori_downloaded\" value=\"" .htmlspecialchars($user['downloaded']) . "\" />".$lang_userdetails['change_field_value_migrated'], 1);
//		tr($lang_userdetails['row_seeding_karma'], "<input disabled type=\"text\" size=\"60\" name=\"bonus\" value=\"" .number_format($user['seedbonus'], 1) . "\" /><input type=\"hidden\" name=\"ori_bonus\" value=\"" .number_format($user['seedbonus'], 1) . "\" />".$lang_userdetails['change_field_value_migrated'], 1);
//		tr($lang_userdetails['row_invites'], "<input disabled type=\"text\" size=\"60\" name=\"invites\" value=\"" .htmlspecialchars($user['invites']) . "\" />".$lang_userdetails['change_field_value_migrated'], 1);

        tr($lang_userdetails['row_amount_uploaded'], "<input type=\"text\" size=\"60\" name=\"uploaded\" value=\"" . htmlspecialchars($user['uploaded']) . "\" /><input type=\"hidden\" name=\"ori_uploaded\" value=\"" . htmlspecialchars($user['uploaded']) . "\" />", 1);
        tr($lang_userdetails['row_amount_downloaded'], "<input type=\"text\" size=\"60\" name=\"downloaded\" value=\"" .htmlspecialchars($user['downloaded']) . "\" /><input type=\"hidden\" name=\"ori_downloaded\" value=\"" .htmlspecialchars($user['downloaded']) . "\" />", 1);
        tr($lang_userdetails['row_seeding_karma'], "<input type=\"text\" size=\"60\" name=\"bonus\" value=\"" .number_format($user['seedbonus'], 1) . "\" /><input type=\"hidden\" name=\"ori_bonus\" value=\"" .number_format($user['seedbonus'], 1) . "\" />", 1);
        tr($lang_userdetails['row_invites'], "<input type=\"text\" size=\"60\" name=\"invites\" value=\"" .htmlspecialchars($user['invites']) . "\" />", 1);
	}
	tr($lang_userdetails['row_passkey'], "<input name=\"resetkey\" value=\"yes\" type=\"checkbox\" />".$lang_userdetails['checkbox_reset_passkey'], 1);

	print("<tr><td class=\"toolbox\" colspan=\"2\" align=\"center\"><input type=\"submit\" class=\"btn\" value=\"".$lang_userdetails['submit_okay']."\" /></td></tr>\n");
	print("</table>\n");
	print("</form>\n");
	end_frame();
	if (get_user_class() >= $cruprfmanage_class)
	{
		begin_frame($lang_userdetails['text_delete_user'], true);
		print("<form method=\"post\" action=\"delacctadmin.php\" name=\"deluser\">
		<input name=\"userid\" size=\"10\" type=\"hidden\" value=\"". $user["id"] ."\" />
		<input name=\"delenable\" type=\"checkbox\" onclick=\"if (this.checked) {enabledel('".$lang_userdetails['js_delete_user_note']."');}else{disabledel();}\" /><input name=\"submit\" type=\"submit\" value=\"".$lang_userdetails['submit_delete']."\" disabled=\"disabled\" /></form>");
		end_frame();
	}
}
end_main_frame();
stdfoot();
?>
