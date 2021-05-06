<?php
ob_start();
require_once("../include/bittorrent.php");
dbconn();
loggedinorreturn();
if (get_user_class() < UC_SYSOP) {
	die("access denied.");
}
$config = nexus_config('nexus.mysql');
mysql_connect($config['host'],$config['username'],$config['password'], $config['database'], $config['port']);
stdhead("Manage Locations");
begin_main_frame("",false,100);
begin_frame("Manage Locations",true,10,"100%","center");

$sure = $_GET['sure'] ?? '';
if($sure == "yes") {
	$delid = $_GET['delid'];
	$query = "DELETE FROM locations WHERE id=" .sqlesc($delid) . " LIMIT 1";
	$sql = sql_query($query);
	echo("Location successfuly removed, click <a class=altlink href=" . $_SERVER['REQUEST_URI'] .">here</a> to go back.");
	end_frame();
	stdfoot();
	die();
}
$delid = intval($_GET['delid'] ?? 0);
if($delid > 0) {
	echo("Are you sure you would like to delete this Location?( <strong><a href='". $_SERVER['REQUEST_URI'] . "?delid=$delid&sure=yes'>Yes!</a></strong> / <strong><a href='". $_SERVER['REQUEST_URI'] . "'>No</a></strong> )");
	end_frame();
	stdfoot();
	die();
}

$edited = intval($_GET['edited'] ?? 0);
if($edited == 1) {
	$id = intval($_GET['id'] ?? 0);
	$name = $_GET['name'];
	$flagpic = $_GET['flagpic'];
	$location_main = $_GET['location_main'];
	$location_sub = $_GET['location_sub'];
	$start_ip = $_GET['start_ip'];
	$end_ip = $_GET['end_ip'];
	$theory_upspeed = $_GET['theory_upspeed'];
	$practical_upspeed = $_GET['practical_upspeed'];
	$theory_downspeed = $_GET['theory_downspeed'];
	$practical_downspeed = $_GET['practical_downspeed'];

	if(validip_format($start_ip) && validip_format($end_ip))
	{
		if(ip2long($end_ip) > ip2long($start_ip))
		{
			$query = "UPDATE locations SET name = " . sqlesc($name) .",flagpic = " . sqlesc($flagpic) . ",location_main = " . sqlesc($location_main). ",location_sub= " . sqlesc($location_sub) . ",start_ip = " . sqlesc($start_ip) .  ",end_ip = " . sqlesc($end_ip) . ",theory_upspeed = " . sqlesc($theory_upspeed) .  ",practical_upspeed = " . sqlesc($practical_upspeed) .  ",theory_downspeed = " . sqlesc($theory_downspeed) .  ",practical_downspeed = " . sqlesc($practical_downspeed). " WHERE id=".sqlesc($id);
			$sql = sql_query($query) or sqlerr(__FILE__, __LINE__);
			if($sql)
			{
				stdmsg("Success!","Location has been edited, click <a class=altlink href=" . $_SERVER['REQUEST_URI'] .">here</a> to go back");
				stdfoot();
				die();
			}
		}
		else
			echo("<p><strong>The end IP address should be larger than the start one, or equal for single IP check!</strong></p>");
	}
	else
		echo("<p><strong>Invalid IP Address Format !!! </strong></p>");

}

$editid = $_GET['editid'] ?? 0;
if($editid > 0) {

	$query = "SELECT * FROM locations WHERE id=" . sqlesc($editid);
	$sql = sql_query($query);
	$row = mysql_fetch_array($sql);

	$name = $row['name'];
	$flagpic = $row['flagpic'];
	$location_main = $row['location_main'];
	$location_sub = $row['location_sub'];
	$start_ip = $row['start_ip'];
	$end_ip = $row['end_ip'];
	$theory_upspeed = $row['theory_upspeed'];
	$practical_upspeed = $row['practical_upspeed'];
	$theory_downspeed = $row['theory_downspeed'];
	$practical_downspeed = $row['practical_downspeed'];

	echo("<form name='form1' method='get' action='" . $_SERVER['REQUEST_URI'] . "'>");
	echo("<input type='hidden' name='id' value='$editid'><table class=main cellspacing=0 cellpadding=5 width=50%>");
	echo("<tr><td class=colhead align=center colspan=2>Editing Locations</td><input type='hidden' name='edited' value='1'></tr>");
	echo("<tr><td class=rowhead>Name:</td><td class=rowfollow align=left><input type='text' size=10 name='name' value='$name'></td></tr>");
	echo("<tr><td class=rowhead><nobr>Main Location:</nobr></td><td class=rowfollow align=left><input type='text' size=50 name='location_main' value='$location_main'></td></tr>");
	echo("<tr><td class=rowhead><nobr>Sub Location:</nobr></td><td class=rowfollow align=left><input type='text' size=50 name='location_sub' value='$location_sub'></td></tr>");
	echo("<tr><td class=rowhead><nobr>Start IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='start_ip' value='" . $start_ip . "'></td></tr>");
	echo("<tr><td class=rowhead><nobr>End IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='end_ip' value='" . $end_ip. "'></td></tr>");
	echo("<tr><td class=rowhead><nobr>Theory Up:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='theory_upspeed' value='$theory_upspeed'></td></tr>");
	echo("<tr><td class=rowhead><nobr>Theory Down:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='theory_downspeed' value='$theory_downspeed'></td></tr>");
	echo("<tr><td class=rowhead><nobr>Practical Up:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='practical_upspeed' value='$practical_upspeed'></td></tr>");
	echo("<tr><td class=rowhead><nobr>Practical Down:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='practical_downspeed' value='$practical_downspeed'></td></tr>");
	echo("<tr><td class=rowhead>Picture:</td><td class=rowfollow align=left><input type='text' size=50 name='flagpic' value='$flagpic'></td></tr>");
	echo("<tr><td class=toolbox align=center colspan=2><input class=btn type='Submit'></td></tr>");
	echo("</table></form>");
	end_frame();
	stdfoot();
	die();
}

$add = $_GET['add'] ?? '';
$success = false;
if($add == 'true') {
	$name = $_GET['name'];
	$flagpic = $_GET['flagpic'];
	$location_main = $_GET['location_main'];
	$location_sub = $_GET['location_sub'];
	$start_ip = $_GET['start_ip'];
	$end_ip = $_GET['end_ip'];
	$theory_upspeed = $_GET['theory_upspeed'];
	$practical_upspeed = $_GET['practical_upspeed'];
	$theory_downspeed = $_GET['theory_downspeed'];
	$practical_downspeed = $_GET['practical_downspeed'];

	if(validip_format($start_ip) && validip_format($end_ip))
	{
		if(ip2long($end_ip) > ip2long($start_ip))
		{
			$query = "INSERT INTO locations (name, flagpic, location_main, location_sub, start_ip, end_ip, theory_upspeed, practical_upspeed, theory_downspeed, practical_downspeed) VALUES (" . sqlesc($name) ."," . sqlesc($flagpic) . "," . sqlesc($location_main). "," . sqlesc($location_sub) . "," . sqlesc($start_ip) .  "," . sqlesc($end_ip) . "," . sqlesc($theory_upspeed) .  "," . sqlesc($practical_upspeed) .  "," . sqlesc($theory_downspeed) .  "," . sqlesc($practical_downspeed) . ")";
			$sql = sql_query($query)  or sqlerr(__FILE__, __LINE__);
			if($sql) {
				$success = true;
			} else {
				$success = false;
			}
		}
		else
			echo("<p><strong>The end IP address should be larger than the start one, or equal for single IP check!</strong></p>");
	}
	else
		echo("<p><strong>Invalid IP Address Format !!! </strong></p>");

}

echo("<form name='form1' method='get' action='" . $_SERVER['REQUEST_URI'] . "'>");
echo("<table class=main cellspacing=0 cellpadding=5 width=48% align= left>");
echo("<tr><td class=colhead align=center colspan=2>Add New Locations</td></tr>");
echo("<tr><td class=rowhead>Name:</td><td class=rowfollow align=left><input type='text' size=10 name='name'></td></tr>");
echo("<tr><td class=rowhead><nobr>Main Location:</nobr></td><td class=rowfollow align=left><input type='text' size=50 name='location_main'></td></tr>");
echo("<tr><td class=rowhead><nobr>Sub Location:</nobr></td><td class=rowfollow align=left><input type='text' size=50 name='location_sub'></td></tr>");
echo("<tr><td class=rowhead><nobr>Start IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='start_ip'></td></tr>");
echo("<tr><td class=rowhead><nobr>End IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='end_ip'></td></tr>");
echo("<tr><td class=rowhead><nobr>Theory Up:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='theory_upspeed'></td></tr>");
echo("<tr><td class=rowhead><nobr>Theory Down:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='theory_downspeed'></td></tr>");
echo("<tr><td class=rowhead><nobr>Practical Up:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='practical_upspeed'></td></tr>");
echo("<tr><td class=rowhead><nobr>Practical Down:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='practical_downspeed'></td></tr>");
echo("<tr><td class=rowhead>Picture:</td><td class=rowfollow align=left><input type='text' size=50 name='flagpic'><input type='hidden' name='add' value='true'></td></tr>");
echo("<tr><td class=toolbox align=center colspan=2><input class=btn type='Submit'></td></tr>");
echo("</table>");
echo("</form>");

$range_start_ip = $_GET['range_start_ip'] ?? '';
$range_end_ip = $_GET['range_end_ip'] ?? '';

echo("<form name='form2' method='get' action='" . $_SERVER['REQUEST_URI'] . "'>");
echo("<table class=main cellspacing=0 cellpadding=5 width=48% align=right>");
echo("<tr><td class=colhead align=center colspan=2>Check IP Range</td></tr>");
echo("<tr><td class=rowhead><nobr>Start IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='range_start_ip' value='" . $range_start_ip . "'></td></tr>");
echo("<tr><td class=rowhead><nobr>End IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='range_end_ip' value='" . $range_end_ip . "'><input type='hidden' name='check_range' value='true'></td></tr>");
echo("<tr><td class=toolbox align=center colspan=2><input class=btn type='Submit'></td></tr>");
echo("</table>");
echo("</form>");
///////////////////// E X I S T I N G C A T E G O R I E S \\\\\\\\\\\\\\\\\\\\\\\\\\\\

print("<br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br />");


unset($wherea);
$wherea = '';
$check_range = $_GET['check_range'] ?? '';
if($check_range == 'true') {

	//stderr("",$range_start_ip . $range_end_ip . validip_format($range_start_ip) . validip_format($range_end_ip));
	if(validip_format($range_start_ip) && validip_format($range_end_ip))
	{
		if(ip2long($range_end_ip) > ip2long($range_start_ip))
		{
			$wherea = "WHERE INET_ATON(start_ip) <=" . ip2long($range_start_ip) . " AND INET_ATON(end_ip) >=" . ip2long($range_end_ip);
			echo("<p><strong>Conforming Locations:</strong></p>");
		}
		else
			echo("<p><strong>The end IP address should be larger than the start one, or equal for single IP check!</strong></p>");
	}
	else
		echo("<p><strong>Invalid IP Address Format !!! </strong></p>");
}
else
{
	echo("<p><strong>" .  ($success == true ? "(Updated!)" : "") . "Existing Locations:</strong></p>");
}
echo("<table class=main cellspacing=0 cellpadding=5>");
echo("<td class=colhead align=center><b>ID</b></td> <td class=colhead align=left><b>Name</b></td> <td class=colhead align=center><b>Pic</b></td> <td class=colhead align=center><b><nobr>Main Location</nobr></b></td> <td class=colhead align=center><b><nobr>Sub Location</nobr></b></td> <td class=colhead align=center><b>Start IP</b></td> <td class=colhead align=center><b>End IP</b></td> <td class=colhead align=center><b>T.U</b></td> <td class=colhead align=center><b>P.U</b></td>  <td class=colhead align=center><b>T.D</b></td> <td class=colhead align=center><b>P.D</b></td> <td class=colhead align=center><b>Edit</b></td><td class=colhead align=center><b>Delete</b></td>");

$res = sql_query("SELECT COUNT(*) FROM locations ".$wherea);
$row = mysql_fetch_array($res);
$count = $row[0];
$perpage = 50;
list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, "location.php?");

$query = "SELECT * FROM locations " . $wherea ." ORDER BY name ASC, start_ip ASC ".$limit;
$sql = sql_query($query);
$maxlen_sub_location = 40;
while ($row = mysql_fetch_array($sql)) {
	$id = $row['id'];
	$name = $row['name'];
	$flagpic = $row['flagpic'];
	$location_main = $row['location_main'];
	$location_sub = $row['location_sub'];
	$start_ip = $row['start_ip'];
	$end_ip = $row['end_ip'];
	$theory_upspeed = $row['theory_upspeed'];
	$practical_upspeed = $row['practical_upspeed'];
	$theory_downspeed = $row['theory_downspeed'];
	$practical_downspeed = $row['practical_downspeed'];

	$count_location_sub=strlen($location_sub);
	if($count_location_sub > $maxlen_sub_location)
		$location_sub=substr($location_sub, 0, $maxlen_sub_location) . "..";

	echo("<tr><td class=rowfollow align=center><strong>$id</strong></td>" .
	"<td class=rowfollow align=left><strong>$name</strong></td>" .
	"<td class=rowfollow align=center>" . ($flagpic != "" ? "<img src='" . get_protocol_prefix() . "$BASEURL/pic/location/$flagpic' border='0' />" : "-") . "</td>" .
	"<td class=rowfollow align=left>$location_main</td>" .
	"<td class=rowfollow align=left>$location_sub</td>" .
	"<td class=rowfollow align=left>" . $start_ip . "</td>" .
	"<td class=rowfollow align=left>" . $end_ip . "</td>" .
	"<td class=rowfollow align=left>$theory_upspeed</td>" .
	"<td class=rowfollow align=left>$practical_upspeed</td>" .
	"<td class=rowfollow align=left>$theory_downspeed</td>" .
	"<td class=rowfollow align=left>$practical_downspeed</td>" .
	"<td class=rowfollow align=center><a href='" . $_SERVER['REQUEST_URI'] . "?editid=$id'>Edit</a></td>".
	"<td class=rowfollow align=center><a href='" . $_SERVER['REQUEST_URI'] . "?delid=$id'>Remove</a></td>" .
	"</tr>");
}
print("</table>");
echo $pagerbottom;

end_frame();
end_frame();
stdfoot();

?>
