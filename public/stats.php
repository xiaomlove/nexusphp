<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
if (get_user_class() < UC_MODERATOR)
	stderr("Error", "Permission denied.");

stdhead("Stats");
?>

<STYLE TYPE="text/css" MEDIA=screen>
  a.colheadlink:link, a.colheadlink:visited{
	font-weight: bold;
	color: #FFFFFF;
	text-decoration: none;
	}

	a.colheadlink:hover {
  	text-decoration: underline;
	}
</STYLE>

<?php
begin_main_frame();

$res = sql_query("SELECT COUNT(*) FROM torrents") or sqlerr(__FILE__, __LINE__);
$n = mysql_fetch_row($res);
$n_tor = $n[0];

$res = sql_query("SELECT COUNT(*) FROM peers") or sqlerr(__FILE__, __LINE__);
$n = mysql_fetch_row($res);
$n_peers = $n[0];

$uporder = $_GET['uporder'] ?? '';
$catorder = $_GET["catorder"] ?? '';

if ($uporder == "lastul")
	$orderby = "last DESC, name";
elseif ($uporder == "torrents")
	$orderby = "n_t DESC, name";
elseif ($uporder == "peers")
	$orderby = "n_p DESC, name";
else
	$orderby = "name";

$query = "SELECT u.id, u.username AS name, MAX(t.added) AS last, COUNT(DISTINCT t.id) AS n_t, COUNT(p.id) as n_p
	FROM users as u LEFT JOIN torrents as t ON u.id = t.owner LEFT JOIN peers as p ON t.id = p.torrent WHERE u.class = 3
	GROUP BY u.id UNION SELECT u.id, u.username AS name, MAX(t.added) AS last, COUNT(DISTINCT t.id) AS n_t, COUNT(p.id) as n_p
	FROM users as u LEFT JOIN torrents as t ON u.id = t.owner LEFT JOIN peers as p ON t.id = p.torrent WHERE u.class > 3
	GROUP BY u.id ORDER BY $orderby";

$res = sql_query($query) or sqlerr(__FILE__, __LINE__);

if (mysql_num_rows($res) == 0)
	stdmsg("Sorry...", "No uploaders.");
else
{
	begin_frame("Uploader Activity", True);
	begin_table();
	print("<tr>\n
	<td class=colhead><a href=\"" . $_SERVER['PHP_SELF'] . "?uporder=uploader&catorder=$catorder\" class=colheadlink>Uploader</a></td>\n
	<td class=colhead><a href=\"" . $_SERVER['PHP_SELF'] . "?uporder=lastul&catorder=$catorder\" class=colheadlink>Last Upload</a></td>\n
	<td class=colhead><a href=\"" . $_SERVER['PHP_SELF'] . "?uporder=torrents&catorder=$catorder\" class=colheadlink>Torrents</a></td>\n
	<td class=colhead>Perc.</td>\n
	<td class=colhead><a href=\"" . $_SERVER['PHP_SELF'] . "?uporder=peers&catorder=$catorder\" class=colheadlink>Peers</a></td>\n
	<td class=colhead>Perc.</td>\n
	</tr>\n");
	while ($uper = mysql_fetch_array($res))
	{
		print("<tr><td>" . get_username($uper['id']) . "</td>\n");
		print("<td " . ($uper['last']?(">".$uper['last']." (".get_elapsed_time(strtotime($uper['last']))." ago)"):"align=center>---") . "</td>\n");
		print("<td align=right>" . $uper['n_t'] . "</td>\n");
		print("<td align=right>" . ($n_tor > 0?number_format(100 * $uper['n_t']/$n_tor,1)."%":"---") . "</td>\n");
		print("<td align=right>" . $uper['n_p']."</td>\n");
		print("<td align=right>" . ($n_peers > 0?number_format(100 * $uper['n_p']/$n_peers,1)."%":"---") . "</td></tr>\n");
	}
	end_table();
	end_frame();
}

if ($n_tor == 0)
	stdmsg("Sorry...", "No categories defined!");
else
{
  if ($catorder == "lastul")
		$orderby = "last DESC, c.name";
	elseif ($catorder == "torrents")
		$orderby = "n_t DESC, c.name";
	elseif ($catorder == "peers")
		$orderby = "n_p DESC, name";
	else
		$orderby = "c.name";

  $res = sql_query("SELECT c.name, MAX(t.added) AS last, COUNT(DISTINCT t.id) AS n_t, COUNT(p.id) AS n_p
	FROM categories as c LEFT JOIN torrents as t ON t.category = c.id LEFT JOIN peers as p
	ON t.id = p.torrent GROUP BY c.id ORDER BY $orderby") or sqlerr(__FILE__, __LINE__);

	begin_frame("Category Activity", True);
	begin_table();
	print("<tr><td class=colhead><a href=\"" . $_SERVER['PHP_SELF'] . "?uporder=$uporder&catorder=category\" class=colheadlink>Category</a></td>
	<td class=colhead><a href=\"" . $_SERVER['PHP_SELF'] . "?uporder=$uporder&catorder=lastul\" class=colheadlink>Last Upload</a></td>
	<td class=colhead><a href=\"" . $_SERVER['PHP_SELF'] . "?uporder=$uporder&catorder=torrents\" class=colheadlink>Torrents</a></td>
	<td class=colhead>Perc.</td>
	<td class=colhead><a href=\"" . $_SERVER['PHP_SELF'] . "?uporder=$uporder&catorder=peers\" class=colheadlink>Peers</a></td>
	<td class=colhead>Perc.</td></tr>\n");
	while ($cat = mysql_fetch_array($res))
	{
		print("<tr><td class=rowhead>" . $cat['name'] . "</b></a></td>");
		print("<td " . ($cat['last']?(">".$cat['last']." (".get_elapsed_time(strtotime($cat['last']))." ago)"):"align = center>---") ."</td>");
		print("<td align=right>" . $cat['n_t'] . "</td>");
		print("<td align=right>" . number_format(100 * $cat['n_t']/$n_tor,1) . "%</td>");
		print("<td align=right>" . $cat['n_p'] . "</td>");
		print("<td align=right>" . ($n_peers > 0?number_format(100 * $cat['n_p']/$n_peers,1)."%":"---") . "</td>\n");
	}
	end_table();
	end_frame();
}

end_main_frame();
stdfoot();
die;
?>
