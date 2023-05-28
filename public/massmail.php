<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
if (get_user_class() < UC_SYSOP)
stderr("Error", "Permission denied.");
$class = intval($_POST["class"] ?? 0);
	if ($class)
		int_check($class,true);

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
    $or = $_POST["or"] ?? '';
    if (!in_array($or, ["<", ">", "=", "<=", ">="], true)) {
        stderr("Error", "Invalid symbol!");
    }
$res = sql_query("SELECT id, username, email FROM users WHERE class $or ".mysql_real_escape_string($class)) or sqlerr(__FILE__, __LINE__);

$subject = substr(htmlspecialchars(trim($_POST["subject"])), 0, 80);
if ($subject == "") $subject = "(no subject)";
$subject = "Fw: $subject";

$message1 = htmlspecialchars(trim($_POST["message"]));
if ($message1 == "") stderr("Error", "Empty message!");

while($arr=mysql_fetch_array($res)){

$to = $arr["email"];


$message = "Message received from ".$SITENAME." on " . date("Y-m-d H:i:s") . ".\n" .
"---------------------------------------------------------------------\n\n" .
$message1 . "\n\n" .
"---------------------------------------------------------------------\n$SITENAME\n";

$success = sent_mail($to,$SITENAME,$SITEEMAIL,$subject,$message,"Mass Mail",false);
}


if ($success)
stderr("Success", "Messages sent.");
else
stderr("Error", "Try again.");

}

stdhead("Mass E-mail Gateway");
?>

<p><table border=0 class=main cellspacing=0 cellpadding=0><tr>
<td class=embedded style='padding-left: 10px'><font size=3><b>Send mass e-mail to all members</b></font></td>
</tr></table></p>
<table border=1 cellspacing=0 cellpadding=5>
<form method=post action=massmail.php>

<?php
if (get_user_class() == UC_MODERATOR && $CURUSER["class"] > UC_POWER_USER)
printf("<input type=hidden name=class value={$CURUSER['class']}\n");
else
{
    $prefix = '';
print("<tr><td class=rowhead>Classe</td><td colspan=2 align=left><select name=or><option value='<'><<option value='>'>><option value='='>=<option value='<='><=<option value='>='>>=</select><select name=class>\n");
if (get_user_class() == UC_MODERATOR)
$maxclass = UC_POWER_USER;
else
$maxclass = get_user_class() - 1;
for ($i = 0; $i <= $maxclass; ++$i)
print("<option value=$i" . ($CURUSER["class"] == $i ? " selected" : "") . ">$prefix" . get_user_class_name($i,false,true,true) . "\n");
print("</select></td></tr>\n");
}
?>


<tr><td class=rowhead>Subject</td><td><input type=text name=subject size=80></td></tr>
<tr><td class=rowhead>Body</td><td><textarea name=message cols=80 rows=20></textarea></td></tr>
<tr><td colspan=2 align=center><input type=submit value="Send" class=btn></td></tr>
</form>
</table>

<?php
stdfoot();
