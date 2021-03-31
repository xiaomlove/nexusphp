<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
if (get_user_class() < UC_MODERATOR)
	stderr("Error", "Permission denied.");
$res2 = sql_query("SELECT agent,count(*) as counts FROM peers  GROUP BY agent order by agent asc") or sqlerr();
stdhead("All Clients");
print("<table align=center border=3 cellspacing=0 cellpadding=5>\n");
print("<tr><td class=colhead>Client</td><td class=colhead>Counts</td></tr>\n");
while($arr2 = mysql_fetch_assoc($res2))
{
	print("</a></td><td align=left>{$arr2['agent']}</td><td align=left>{$arr2['counts']}</td></tr>\n");
}
print("</table>\n");
stdfoot();
