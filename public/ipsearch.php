<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();

if (get_user_class() < $userprofile_class)
	permissiondenied();
else
{
	$ip = trim($_GET['ip']);
	if ($ip)
	{
		$regex = "/^(((1?\d{1,2})|(2[0-4]\d)|(25[0-5]))(\.\b|$)){4}$/";
		if (!filter_var($ip, FILTER_VALIDATE_IP))
		{
			stderr($lang_ipsearch['std_error'], $lang_ipsearch['std_invalid_ip']);
		}
	}

	$mask = trim($_GET['mask'] ?? '');
	if ($mask == "" || $mask == "255.255.255.255")
	{
		$where1 = "u.ip = '$ip'";
		$where2 = "iplog.ip = '$ip'";
		$dom = @gethostbyaddr($ip);
		if ($dom == $ip || @gethostbyname($dom) != $ip)
			$addr = "";
		else
			$addr = $dom;
	}
	else
	{
		if (substr($mask,0,1) == "/")
		{
			$n = substr($mask, 1, strlen($mask) - 1);
				if (!is_numeric($n) or $n < 0 or $n > 32)
				{
					stderr($lang_ipsearch['std_error'], $lang_ipsearch['std_invalid_subnet_mask']);
				}
				else
					$mask = long2ip(pow(2,32) - pow(2,32-$n));
		}
		elseif (!preg_match($regex, $mask))
		{
			stderr($lang_ipsearch['std_error'], $lang_ipsearch['std_invalid_subnet_mask']);
		}
		$where1 = "INET_ATON(u.ip) & INET_ATON('$mask') = INET_ATON('$ip') & INET_ATON('$mask')";
		$where2 = "INET_ATON(iplog.ip) & INET_ATON('$mask') = INET_ATON('$ip') & INET_ATON('$mask')";
		$addr = "Mask: $mask";
	}

	stdhead($lang_ipsearch['head_search_ip_history']);
	begin_main_frame();

	print("<h1 align=\"center\">".$lang_ipsearch['text_search_ip_history']."</h1>\n");
	print("<form method=\"get\" action=\"\">");
	print("<table align=center border=1 cellspacing=0 width=115 cellpadding=5>\n");
	tr($lang_ipsearch['row_ip']."<font color=red>*</font>", "<input type=\"text\" name=\"ip\" size=\"40\" value=\"".htmlspecialchars($ip)."\" />", 1);
	tr("<nobr>".$lang_ipsearch['row_subnet_mask']."</nobr>", "<input type=\"text\" name=\"mask\" size=\"40\" value=\"" . htmlspecialchars($mask) . "\" />", 1);
	print("<tr><td align=\"right\" colspan=\"2\"><input type=\"submit\" value=\"".$lang_ipsearch['submit_search']."\"/></td></tr>");
	print("</table></form>\n");
	if ($ip)
	{
	$queryc = "SELECT COUNT(*) FROM
(
SELECT u.id FROM users AS u WHERE $where1
UNION SELECT u.id FROM users AS u RIGHT JOIN iplog ON u.id = iplog.userid WHERE $where2
GROUP BY u.id
) AS ipsearch";

	$res = sql_query($queryc) or sqlerr(__FILE__, __LINE__);
	$row = mysql_fetch_array($res);
	$count = $row[0];

	if ($count == 0)
	{
		print("<p align=\"center\">".$lang_ipsearch['text_no_users_found']."</p>\n");
		end_main_frame();
		stdfoot();
		die;
	}

	$order = $_GET['order'] ?? '';
	$page = intval($_GET["page"] ?? 0);
	$perpage = 20;

	list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, "{$_SERVER['PHP_SELF']}?ip=$ip&mask=$mask&order=$order&");

	if ($order == "added")
		$orderby = "added DESC";
	elseif ($order == "username")
		$orderby = "UPPER(username) ASC";
	elseif ($order == "email")
		$orderby = "email ASC";
	elseif ($order == "last_ip")
		$orderby = "last_ip ASC";
	elseif ($order == "last_access")
		$orderby = "last_ip ASC";
	else
		$orderby = "access DESC";

	$query = "SELECT * FROM (
SELECT u.id, u.username, u.ip AS ip, u.ip AS last_ip, u.last_access, u.last_access AS access, u.email, u.invited_by, u.added, u.class, u.uploaded, u.downloaded, u.donor, u.enabled, u.warned
FROM users AS u
WHERE $where1
UNION SELECT u.id, u.username, iplog.ip AS ip, u.ip as last_ip, u.last_access, max(iplog.access) AS access, u.email, u.invited_by, u.added, u.class, u.uploaded, u.downloaded, u.donor, u.enabled, u.warned
FROM users AS u
RIGHT JOIN iplog ON u.id = iplog.userid
WHERE $where2
GROUP BY u.id ) as ipsearch
GROUP BY id
ORDER BY $orderby
$limit";

	$res = sql_query($query) or sqlerr(__FILE__, __LINE__);

	print("<h1 align=\"center\">".$count.$lang_ipsearch['text_users_used_the_ip'].$ip."</h1>");

	print("<table width=1200 border=1 cellspacing=0 cellpadding=5 align=center>\n");
	print("<tr><td class=colhead align=center><a class=colhead href=\"?ip=$ip&mask=$mask&order=username\">".$lang_ipsearch['col_username']."</a></td>".
"<td class=colhead align=center><a class=colhead href=\"?ip=$ip&mask=$mask&order=last_ip\">".$lang_ipsearch['col_last_ip']."</a></td>".
"<td class=colhead align=center><a class=colhead href=\"?ip=$ip&mask=$mask&order=last_access\">".$lang_ipsearch['col_last_access']."</a></td>".
"<td class=colhead align=center>".$lang_ipsearch['col_ip_num']."</td>".
"<td class=colhead align=center><a class=colhead href=\"?ip=$ip&mask=$mask\">".$lang_ipsearch['col_last_access_on']."</a></td>".
"<td class=colhead align=center><a class=colhead href=\"?ip=$ip&mask=$mask&order=added\">".$lang_ipsearch['col_added']."</a></td>".
"<td class=colhead align=center>".$lang_ipsearch['col_invited_by']."</td>");

	while ($user = mysql_fetch_array($res))
	{
		if ($user['added'] == '0000-00-00 00:00:00' || $user['added'] == null)
			$added = $lang_ipsearch['text_not_available'];
		else $added = gettime($user['added']);
		if ($user['last_access'] == '0000-00-00 00:00:00' || $user['added'] == null)
			$lastaccess = $lang_ipsearch['text_not_available'];
		else $lastaccess = gettime($user['last_access']);

		if ($user['last_ip'])
			$ipstr = $user['last_ip'];
		else
			$ipstr = $lang_ipsearch['text_not_available'];

		$resip = sql_query("SELECT ip FROM iplog WHERE userid=" . sqlesc($user['id']) . " GROUP BY iplog.ip") or sqlerr(__FILE__, __LINE__);
$iphistory = mysql_num_rows($resip);

		if ($user["invited_by"] > 0)
		{
			$invited_by = get_username($user['invited_by']);
		}
		else
			$invited_by = $lang_ipsearch['text_not_available'];

		echo "<tr><td align=\"center\">" .
get_username($user['id'])."</td>".
"<td align=\"center\">" . $ipstr . "</td>
<td align=\"center\">" . $lastaccess . "</td>
<td align=\"center\"><a href=\"iphistory.php?id=" . $user['id'] . "\">" . $iphistory. "</a></td>
<td align=\"center\">" . gettime($user['access']) . "</td>
<td align=\"center\">" . gettime($user['added']) . "</td>
<td align=\"center\">" . $invited_by . "</td>
</tr>\n";
	}
	echo "</table>";

	echo $pagerbottom;
	}
	end_main_frame();
	stdfoot();
}
?>
