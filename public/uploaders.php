<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();

if (get_user_class() < UC_UPLOADER)
    permissiondenied();

$year=intval($_GET['year'] ?? 0);
if (!$year || $year < 2000)
$year=date('Y');
$month=intval($_GET['month'] ?? 0);
if (!$month || $month<=0 || $month>12)
$month=date('m');
$order=$_GET['order'] ?? '';
if (!in_array($order, array('username', 'torrent_size', 'torrent_count')))
	$order='username';
if ($order=='username')
	$order .=' ASC';
else $order .= ' DESC';
stdhead($lang_uploaders['head_uploaders']);
begin_main_frame();
?>
<div style="width: 940px">
<?php
$year2 = substr($datefounded, 0, 4);
$yearfounded = ($year2 ? $year2 : 2007);
$yearnow=date("Y");

$timestart=strtotime($year."-".$month."-01 00:00:00");
$sqlstarttime=date("Y-m-d H:i:s", $timestart);
$timeend=strtotime("+1 month", $timestart);
$sqlendtime=date("Y-m-d H:i:s", $timeend);

print("<h1 align=\"center\">".$lang_uploaders['text_uploaders']." - ".date("Y-m",$timestart)."</h1>");

$yearselection="<select name=\"year\">";
for($i=$yearfounded; $i<=$yearnow; $i++)
	$yearselection .= "<option value=\"".$i."\"".($i==$year ? " selected=\"selected\"" : "").">".$i."</option>";
$yearselection.="</select>";

$monthselection="<select name=\"month\">";
for($i=1; $i<=12; $i++)
	$monthselection .= "<option value=\"".$i."\"".($i==$month ? " selected=\"selected\"" : "").">".$i."</option>";
$monthselection.="</select>";

?>
<div>
<form method="get" action="?">
<span>
<?php echo $lang_uploaders['text_select_month']?><?php echo $yearselection?>&nbsp;&nbsp;<?php echo $monthselection?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang_uploaders['submit_go']?>" />
</span>
</form>
</div>

<?php
$num = (int) \Nexus\Database\NexusDB::table('users')->where('class', '>=', UC_UPLOADER)->count();
if (!$num)
	print("<p align=\"center\">".$lang_uploaders['text_no_uploaders_yet']."</p>");
else{
?>
<div style="margin-top: 8px">
<?php
	print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" align=\"center\" width=\"97%\"><tr>");
	print("<td class=\"colhead\">".$lang_uploaders['col_username']."</td>");
	print("<td class=\"colhead\">".$lang_uploaders['col_torrents_size']."</td>");
	print("<td class=\"colhead\">".$lang_uploaders['col_torrents_num']."</td>");
	print("<td class=\"colhead\">".$lang_uploaders['col_last_upload_time']."</td>");
	print("<td class=\"colhead\">".$lang_uploaders['col_last_upload']."</td>");
	print("</tr>");
	$startQuoted = \Nexus\Database\NexusDB::getPdo()->quote($sqlstarttime);
	$endQuoted = \Nexus\Database\NexusDB::getPdo()->quote($sqlendtime);
	$ucUploader = (int) UC_UPLOADER;
	$rows = \Nexus\Database\NexusDB::select("SELECT users.id AS userid, users.username AS username, COUNT(torrents.id) AS torrent_count, SUM(torrents.size) AS torrent_size FROM torrents LEFT JOIN users ON torrents.owner=users.id WHERE users.class >= $ucUploader AND torrents.added > $startQuoted AND torrents.added < $endQuoted GROUP BY userid ORDER BY ".$order);
	$hasupuserid=array();
	foreach ($rows as $row)
	{
		$row = (array) $row;
		$row2Obj = \Nexus\Database\NexusDB::table('torrents')->where('owner', (int) $row['userid'])->orderByDesc('id')->select(['id', 'name', 'added'])->first();
		$row2 = $row2Obj ? (array) $row2Obj : [];
		print("<tr>");
		print("<td class=\"colfollow\">".get_username($row['userid'], false, true, true, false, false, true)."</td>");
		print("<td class=\"colfollow\">".($row['torrent_size'] ? mksize($row['torrent_size']) : "0")."</td>");
		print("<td class=\"colfollow\">".$row['torrent_count']."</td>");
		print("<td class=\"colfollow\">".($row2['added'] ? gettime($row2['added']) : $lang_uploaders['text_not_available'])."</td>");
		print("<td class=\"colfollow\">".($row2['name'] ? "<a href=\"details.php?id=".$row2['id']."\">".htmlspecialchars($row2['name'])."</a>" : $lang_uploaders['text_not_available'])."</td>");
		print("</tr>");
		$hasupuserid[]=$row['userid'];
		unset($row2);
	}
	$rows3 = \Nexus\Database\NexusDB::select("SELECT users.id AS userid, users.username AS username, 0 AS torrent_count, 0 AS torrent_size FROM users WHERE class >= ".$ucUploader.(count($hasupuserid) ? " AND users.id NOT IN (".implode(",",$hasupuserid).")" : "")." ORDER BY username ASC");
    $count = 0;
	foreach ($rows3 as $row)
	{
		$row = (array) $row;
		$row2Obj = \Nexus\Database\NexusDB::table('torrents')->where('owner', (int) $row['userid'])->orderByDesc('id')->select(['id', 'name', 'added'])->first();
		$row2 = $row2Obj ? (array) $row2Obj : [];
		print("<tr>");
		print("<td class=\"colfollow\">".get_username($row['userid'], false, true, true, false, false, true)."</td>");
		print("<td class=\"colfollow\">".($row['torrent_size'] ? mksize($row['torrent_size']) : "0")."</td>");
		print("<td class=\"colfollow\">".$row['torrent_count']."</td>");
		print("<td class=\"colfollow\">".($row2['added'] ? gettime($row2['added']) : $lang_uploaders['text_not_available'])."</td>");
		print("<td class=\"colfollow\">".($row2['name'] ? "<a href=\"details.php?id=".$row2['id']."\">".htmlspecialchars($row2['name'])."</a>" : $lang_uploaders['text_not_available'])."</td>");
		print("</tr>");
		$count++;
		unset($row2);
	}
	print("</table>");
?>
</div>
<div style="margin-top: 8px; margin-bottom: 8px;">
<span id="order" onclick="dropmenu(this);"><span style="cursor: pointer;" class="big"><b><?php echo $lang_uploaders['text_order_by']?></b></span>
<span id="orderlist" class="dropmenu" style="display: none"><ul>
<li><a href="?year=<?php echo $year?>&amp;month=<?php echo $month?>&amp;order=username"><?php echo $lang_uploaders['text_username']?></a></li>
<li><a href="?year=<?php echo $year?>&amp;month=<?php echo $month?>&amp;order=torrent_size"><?php echo $lang_uploaders['text_torrent_size']?></a></li>
<li><a href="?year=<?php echo $year?>&amp;month=<?php echo $month?>&amp;order=torrent_count"><?php echo $lang_uploaders['text_torrent_num']?></a></li>
</ul>
</span>
</span>
</div>
<?php
}
?>
</div>
<?php
end_main_frame();
stdfoot();
?>
