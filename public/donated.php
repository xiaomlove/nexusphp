<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
if (get_user_class() < UC_SYSOP)
stderr("Error", "Access denied.");
if ($_SERVER["REQUEST_METHOD"] == "POST")
{
if ($_POST["username"] == "" || $_POST["donated"] == "")
stderr("Error", "Missing form data.");
$username = sqlesc($_POST["username"]);
$donated = sqlesc($_POST["donated"]);

sql_query("UPDATE users SET donated=$donated WHERE username=$username") or sqlerr(__FILE__, __LINE__);
$res = sql_query("SELECT id FROM users WHERE username=$username");
$arr = mysql_fetch_row($res);
if (!$arr)
stderr("Error", "Unable to update account.");
header("Location: " . get_protocol_prefix() . "$BASEURL/userdetails.php?id=$arr[0]");
die;
}
stdhead("Update Users Donated Amounts");
?>
<h1>Update Users Donated Amounts</h1>
<form method=post action=donated.php>
<table border=1 cellspacing=0 cellpadding=5>
<tr><td class=rowhead>User name</td><td><input type=text name=username size=40></td></tr>
<tr><td class=rowhead>Donated</td><td><input type=uploaded name=donated size=5></td></tr>
<tr><td colspan=2 align=center><input type=submit value="Okay" class=btn></td></tr>
</table>
</form>
<?php stdfoot();