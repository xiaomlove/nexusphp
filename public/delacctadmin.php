<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
user_can('user-delete', true);

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
$userid = trim($_POST["userid"]);

if (!$userid)
  stderr("Error", "Please fill out the form correctly.");

$res = sql_query("SELECT * FROM users WHERE id=" . sqlesc($userid)) or sqlerr();
if (mysql_num_rows($res) != 1)
  stderr("Error", "Bad user id or password. Please verify that all entered information is correct.");
$arr = mysql_fetch_assoc($res);

$id = $arr['id'];
$name = $arr['username'];
$userRep = new \App\Repositories\UserRepository();
$userRep->destroy($id);
stderr("Success", "The account <b>".htmlspecialchars($name)."</b> was deleted.",false);
}
stdhead("Delete account");
?>
<h1>Delete account</h1>
<table border=1 cellspacing=0 cellpadding=5>
<form method=post action=delacctadmin.php>
<tr><td class=rowhead>User name</td><td><input size=40 name=userid></td></tr>

<tr><td colspan=2><input type=submit class=btn value='Delete'></td></tr>
</form>
</table>
<?php
stdfoot();
