<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
parked();
if (get_user_class() < UC_MODERATOR)
stderr("Sorry", "Access denied.");

stdhead("Warned Users");
$warned = number_format(get_row_count("users", "WHERE warned='yes'"));
begin_frame("Warned Users: ($warned)", true);
begin_table();

$res = sql_query("SELECT * FROM users WHERE warned=1 AND enabled='yes' ORDER BY (users.uploaded/users.downloaded)") or sqlerr();
$num = mysql_num_rows($res);
print("<table border=1 width=675 cellspacing=0 cellpadding=2><form action=\"nowarn.php\" method=post>\n");
print("<tr align=center><td class=colhead width=90>User Name</td>
 <td class=colhead width=70>Registered</td>
 <td class=colhead width=75>Last access</td>  
 <td class=colhead width=75>User Class</td>
 <td class=colhead width=70>Downloaded</td>
 <td class=colhead width=70>UpLoaded</td>
 <td class=colhead width=45>Ratio</td>
 <td class=colhead width=125>End<br>Of Warning</td>
 <td class=colhead width=65>Remove<br>Warning</td>
 <td class=colhead width=65>Disable<br>Account</td></tr>\n");
for ($i = 1; $i <= $num; $i++)
{
$arr = mysql_fetch_assoc($res);
if ($arr['added'] == '0000-00-00 00:00:00' || $arr['added'] == null)
  $arr['added'] = '-';
if ($arr['last_access'] == '0000-00-00 00:00:00' || $arr['added'] == null)
  $arr['last_access'] = '-';
if($arr["downloaded"] != 0){
$ratio = number_format($arr["uploaded"] / $arr["downloaded"], 3);
} else {
$ratio="---";
}
$ratio = "<font color=" . get_ratio_color($ratio) . ">$ratio</font>";
  $uploaded = mksize($arr["uploaded"]);
  $downloaded = mksize($arr["downloaded"]);
// $uploaded = str_replace(" ", "<br>", mksize($arr["uploaded"]));
// $downloaded = str_replace(" ", "<br>", mksize($arr["downloaded"]));

$added = substr($arr['added'],0,10);
$last_access = substr($arr['last_access'],0,10);
$class=get_user_class_name($arr["class"],false,true,true);

print("<tr><td align=left>" . get_username($arr['id']) ."</td>
  <td align=center>$added</td>
  <td align=center>$last_access</td>
  <td align=center>$class</td>
  <td align=center>$downloaded</td>
  <td align=center>$uploaded</td>
  <td align=center>$ratio</td>
  <td align=center>{$arr['warneduntil']}</td>
  <td bgcolor=\"#008000\" align=center><input type=\"checkbox\" name=\"usernw[]\" value=\"{$arr['id']}\"></td>
  <td bgcolor=\"#FF000\" align=center><input type=\"checkbox\" name=\"desact[]\" value=\"{$arr['id']}\"></td></tr>\n");
}
if (get_user_class() >= UC_ADMINISTRATOR) {
print("<tr><td colspan=10 align=right><input type=\"submit\" name=\"submit\" value=\"Apply Changes\"></td></tr>\n");
print("<input type=\"hidden\" name=\"nowarned\" value=\"nowarned\"></form></table>\n");
}
print("<p>" . ($pagemenu ?? '') . "<br>" . ($browsemenu ?? '') . "</p>");

die;

?>
