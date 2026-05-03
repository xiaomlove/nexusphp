<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();

user_can('userprofile', true);

$userid = intval($_GET["id"] ?? 0);
if (!is_valid_id($userid))
	stderr($lang_iphistory['std_error'], $lang_iphistory['std_invalid_id']);

$username = \Nexus\Database\NexusDB::table('users')->where('id', (int) $userid)->value('username');
if ($username === null)
	stderr($lang_iphistory['error'], $lang_iphistory['text_user_not_found']);

$perpage = 20;

$ipCountVal = \Nexus\Database\NexusDB::table('iplog')->where('userid', (int) $userid)->distinct()->count('access');
$countrows = (int) $ipCountVal + 1;
$order = $_GET['order'] ?? '';

[$pagertop, $pagerbottom, $limit, $offsetStart, $rowsPerPage] = pager($perpage, $countrows, "iphistory.php?id=$userid&order=$order&");

$query = "SELECT u.id, u.ip AS ip, last_access AS access FROM users as u WHERE u.id = $userid
UNION DISTINCT SELECT u.id, iplog.ip as ip, iplog.access as access FROM users AS u
RIGHT JOIN iplog on u.id = iplog.userid WHERE u.id = $userid ORDER BY access DESC $limit";

$ipHistoryRows = \Nexus\Database\NexusDB::select($query);

stdhead($lang_iphistory['head_ip_history_log_for'].$username);
begin_main_frame();

print("<h1 align=\"center\">".$lang_iphistory['text_historical_ip_by'] . get_username($userid)."</h1>");

if ($countrows > $perpage)
echo $pagertop;

print("<table width=500 border=1 cellspacing=0 cellpadding=5 align=center>\n");
print("<tr>\n
<td class=colhead>".$lang_iphistory['col_last_access']."</td>\n
<td class=colhead>".$lang_iphistory['col_ip']."</td>\n
<td class=colhead>".$lang_iphistory['col_hostname']."</td>\n
</tr>\n");
foreach ($ipHistoryRows as $arr)
{
$arr = (array) $arr;
$addr = "";
$ipshow = "";
if ($arr["ip"])
{
$ip = $arr["ip"];
$dom = @gethostbyaddr($arr["ip"]);
if ($dom == $arr["ip"] || @gethostbyname($dom) != $arr["ip"])
$addr = $lang_iphistory['text_not_available'];
else
$addr = $dom;

$ipQuoted = \Nexus\Database\NexusDB::getPdo()->quote($ip);
$queryc = "SELECT COUNT(*) AS c FROM
(
SELECT u.id FROM users AS u WHERE u.ip = $ipQuoted
UNION SELECT u.id FROM users AS u RIGHT JOIN iplog ON u.id = iplog.userid WHERE iplog.ip = $ipQuoted
GROUP BY u.id
) AS ipsearch";
$ipCountRows = \Nexus\Database\NexusDB::select($queryc);
$ipcount = $ipCountRows ? (int) ((array) $ipCountRows[0])['c'] : 0;

if ($ipcount > 1)
$ipshow = "<a href=\"ipsearch.php?ip=". $arr['ip'] ."\">" . $arr['ip'] ."</a> <b>(<font class='striking'>".$lang_iphistory['text_duplicate']."</font>)</b>";
else
$ipshow = "<a href=\"ipsearch.php?ip=". $arr['ip'] ."\">" . $arr['ip'] ."</a>";
}
$date = gettime($arr["access"]);
print("<tr><td>".$date."</td>\n");
print("<td>".$ipshow."</td>\n");
print("<td>".$addr."</td></tr>\n");
}

print("</table>");

echo $pagerbottom;

end_main_frame();
stdfoot();
die;
?>
