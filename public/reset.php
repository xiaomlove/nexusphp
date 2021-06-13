<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
// Reset Lost Password ACTION
if (get_user_class() < UC_ADMINISTRATOR)
stderr("Error", "Permission denied, Administrator Only.");

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
 $username = trim($_POST["username"]);
 $newpassword = trim($_POST["newpassword"]);
 $newpasswordagain = trim($_POST["newpasswordagain"]);

 if (empty($username) || empty($newpassword) || empty($newpasswordagain))
	stderr("Error","Don't leave any fields blank.");

 if ($newpassword != $newpasswordagain)
	stderr("Error","The passwords didn't match! Must've typoed. Try again.");

 if (strlen($newpassword) < 6)
	stderr("Error","Sorry, password is too short (min is 6 chars)");

   $res = sql_query("SELECT * FROM users WHERE username=" . sqlesc($username) . " ") or sqlerr();
$arr = mysql_fetch_assoc($res);
if (get_user_class() <= $arr['class']) {
    $log = "Password Reset For $username by {$CURUSER['username']} denied: operator class => " . get_user_class() . " is not greater than target user => {$arr['class']}";
    write_log($log);
    do_log($log, 'alert');
    stderr("Error","Sorry, you don't have enough permission to reset this user's password.");
}

$id = $arr['id'];
$wantpassword=$newpassword;
$secret = mksecret();
$wantpasshash = md5($secret . $wantpassword . $secret);
sql_query("UPDATE users SET passhash=".sqlesc($wantpasshash).", secret= ".sqlesc($secret)." where id=$id");
write_log("Password Reset For $username by {$CURUSER['username']}");
 if (mysql_affected_rows() != 1)
   stderr("Error", "Unable to RESET PASSWORD on this account.");
 stderr("Success", "The password of account <b>$username</b> is reset , please inform user of this change.",false);
}
stdhead("Reset User's Lost Password");
?>
<table border=1 cellspacing=0 cellpadding=5>
<form method=post>
<tr><td class=colhead align="center" colspan=2>Reset User's Lost Password</td></tr>
<tr><td class=rowhead align="right">User Name:</td><td class=rowfollow><input size=40 name=username></td></tr>
<tr><td class=rowhead align="right">New Password:</td><td class=rowfollow><input type="password" size=40 name=newpassword><br /><font class=small>Minimum is 6 characters</font></td></tr>
<tr><td class=rowhead align="right">Confirm New Password:</td><td class=rowfollow><input type="password" size=40 name=newpasswordagain></td></tr>
<tr><td class=toolbox colspan=2 align="center"><input type=submit class=btn value='Reset'></td></tr>
</form>
</table>
<?php
stdfoot();
