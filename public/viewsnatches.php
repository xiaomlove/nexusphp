<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
parked();
$id = $_GET["id"];
int_check($id,true);

stdhead($lang_viewsnatches['head_snatch_detail']);
begin_main_frame();

$torrent_name = get_single_value("torrents", "name", "WHERE id = ".sqlesc($id));
print("<h1 align=center>".$lang_viewsnatches['text_snatch_detail_for'] . "<a href=details.php?id=" . htmlspecialchars($id) . "><b>".htmlspecialchars($torrent_name)."</b></a></h1>");
$count = get_row_count("snatched", "WHERE finished = 'yes' AND torrentid = ".sqlesc($id));

if ($count){
	$perpage = 25;
	list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, $_SERVER["SCRIPT_NAME"] . "?id=" . htmlspecialchars($id) . "&" );
	print("<p align=center>".$lang_viewsnatches['text_users_top_finished_recently']."</p>");
	print("<table border=1 cellspacing=0 cellpadding=5 align=center width=940>\n");
	print("<tr><td class=colhead align=center>".$lang_viewsnatches['col_username']."</td>".(get_user_class() >= $userprofile_class ? "<td class=colhead align=center>".$lang_viewsnatches['col_ip']."</td>" : "")."<td class=colhead align=center>".$lang_viewsnatches['col_uploaded']."/".$lang_viewsnatches['col_downloaded']."</td><td class=colhead align=center>".$lang_viewsnatches['col_ratio']."</td><td class=colhead align=center>".$lang_viewsnatches['col_se_time']."</td><td class=colhead align=center>".$lang_viewsnatches['col_le_time']."</td><td class=colhead align=center>".$lang_viewsnatches['col_when_completed']."</td><td class=colhead align=center>".$lang_viewsnatches['col_last_action']."</td><td class=colhead align=center>".$lang_viewsnatches['col_report_user']."</td></tr>");

	$res = sql_query("SELECT * FROM snatched WHERE finished='yes' AND torrentid =" . sqlesc($id) . " ORDER BY completedat DESC $limit");

	while ($arr = mysql_fetch_assoc($res))
	{
		//start torrent
		if ($arr["downloaded"] > 0)
		{
			$ratio = number_format($arr["uploaded"] / $arr["downloaded"], 3);
			$ratio = "<font color=" . get_ratio_color($ratio) . ">$ratio</font>";
		}
		elseif ($arr["uploaded"] > 0)
			$ratio = $lang_viewsnatches['text_inf'];
		else
			$ratio = "---";
		$uploaded =mksize($arr["uploaded"]);
		$downloaded = mksize($arr["downloaded"]);
		$seedtime = mkprettytime($arr["seedtime"]);
		$leechtime = mkprettytime($arr["leechtime"]);

		$uprate = $arr["seedtime"] > 0 ? mksize($arr["uploaded"] / ($arr["seedtime"] + $arr["leechtime"])) : mksize(0);
		$downrate = $arr["leechtime"] > 0 ? mksize($arr["downloaded"] / $arr["leechtime"]) : mksize(0);
		//end

		$highlight = $CURUSER["id"] == $arr["userid"] ? " bgcolor=#00A527" : "";
		$userrow = get_user_row($arr['userid']);
		if ($userrow['privacy'] == 'strong'){
			$username = $lang_viewsnatches['text_anonymous'];
			if (get_user_class() >= $viewanonymous_class || $arr["id"] == $CURUSER['id'])
				$username .= "<br />(".get_username($arr['userid']).")";
		}
		else $username = get_username($arr['userid']);
		$reportImage = "<img class=\"f_report\" src=\"pic/trans.gif\" alt=\"Report\" title=\"".$lang_viewsnatches['title_report']."\" />";
		print("<tr$highlight><td class=rowfollow align=center>" . $username ."</td>".(get_user_class() >= $userprofile_class ? "<td class=rowfollow align=center>".$arr['ip']."</td>" : "")."<td class=rowfollow align=center>".$uploaded."@".$uprate.$lang_viewsnatches['text_per_second']."<br />".$downloaded."@".$downrate.$lang_viewsnatches['text_per_second']."</td><td class=rowfollow align=center>$ratio</td><td class=rowfollow align=center>$seedtime</td><td class=rowfollow align=center>$leechtime</td><td class=rowfollow align=center>".gettime($arr['completedat'],true,false)."</td><td class=rowfollow align=center>".gettime($arr['last_action'],true,false)."</td><td class=rowfollow align=center style='padding: 0px'>".($userrow['privacy'] != 'strong' || get_user_class() >= $viewanonymous_class ? "<a href=report.php?user={$arr['userid']}>$reportImage</a>" : $reportImage)."</td></tr>\n");
	}
		print("</table>\n");
		print($pagerbottom);
}
else
{
	stdmsg($lang_viewsnatches['std_sorry'], $lang_viewsnatches['text_no_snatched_users']);
}
end_main_frame();
stdfoot();
?>
