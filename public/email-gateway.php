<?php
require "../include/bittorrent.php";
dbconn();
$id = intval($_GET["id"] ?? 0);
int_check($id,true);

$res = sql_query("SELECT username, class, email FROM users WHERE id=".mysql_real_escape_string($id));
$arr = mysql_fetch_assoc($res) or stderr("Error", "No such user.");
$username = $arr["username"];
if ($arr["class"] < UC_MODERATOR)
	stderr("Error", "The gateway can only be used to e-mail staff members.");

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
	$to = $arr["email"];
	$from = substr(htmlspecialchars(trim($_POST["from"])), 0, 80);
	if ($from == "") $from = "Anonymous";

	$from_email = substr(htmlspecialchars(trim($_POST["from_email"])), 0, 80);
	if ($from_email == "") $from_email = "".$SITEEMAIL."";
	$from_email =  safe_email($from_email);
	if (!$from_email)
    	stderr("Error","You must enter an email address!");	
	if (!check_email($from_email))
  	stderr("Error","Invalid email address!");
	$from = "$from <$from_email>";

	$subject = substr(htmlspecialchars(trim($_POST["subject"])), 0, 80);
	if ($subject == "") $subject = "(No subject)";
	$subject = "Fw: $subject";
	
	$message = htmlspecialchars(trim($_POST["message"]));
	if ($message == "") stderr("Error", "No message text!");

	$message = "Message submitted from ".getip()." at " . date("Y-m-d H:i:s") . ".\n" .
		"Note: By replying to this e-mail you will reveal your e-mail address.\n" .
		"---------------------------------------------------------------------\n\n" .
		$message . "\n\n" .
		"---------------------------------------------------------------------\n$SITENAME E-Mail Gateway\n";

	$success = sent_mail($to,$from,$from_email,$subject,$message,"E-Mail Gateway",false);	

	if ($success)
		stderr("Success", "E-mail successfully queued for delivery.");
	else
		stderr("Error", "The mail could not be sent. Please try again later.");
}

stdhead("E-mail gateway");
?>
<p><table border=0 class=main cellspacing=0 cellpadding=0><tr>
<td class=embedded style='padding-left: 10px'><font size=3><b>Send e-mail to <?php echo $username;?></b></font></td>
</tr></table></p>
<table border=1 cellspacing=0 cellpadding=5>
<form method=post action=email-gateway.php?id=<?php echo $id?>>
<tr><td class=rowhead>Your name</td><td><input type=text name=from size=80></td></tr>
<tr><td class=rowhead>Your e-mail</td><td><input type=text name=from_email size=80></td></tr>
<tr><td class=rowhead>Subject</td><td><input type=text name=subject size=80></td></tr>
<tr><td class=rowhead>Message</td><td><textarea name=message cols=80 rows=20></textarea></td></tr>
<tr><td colspan=2 align=center><input type=submit value="Send" class=btn></td></tr>
</form>
</table>
<p>
<font class=small><b>Note:</b> Your IP-address will be logged and visible to the recipient to prevent abuse.<br />
Make sure to supply a valid e-mail address if you expect a reply.</font>
</p>
<?php stdfoot();
