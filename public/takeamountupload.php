<?php
require "../include/bittorrent.php";
if ($_SERVER["REQUEST_METHOD"] != "POST")
	stderr("Error", "Permission denied!");
dbconn();
loggedinorreturn();                                                    

if (get_user_class() < UC_SYSOP)
	stderr("Sorry", "Permission denied.");

$sender_id = ($_POST['sender'] == 'system' ? 0 : (int)$CURUSER['id']);
$dt = sqlesc(date("Y-m-d H:i:s"));
$msg = trim($_POST['msg']);
$amount = $_POST['amount'];
if (!$msg || !$amount)
	stderr("Error","Don't leave any fields blank.");
if(!is_numeric($amount))
	stderr("Error","amount must be numeric");
$updateset = $_POST['clases'];
if (is_array($updateset)) {
	foreach ($updateset as $class) {
		if (!is_valid_id($class) && $class != 0)
			stderr("Error","Invalid Class");
	}
}else{
	if (!is_valid_id($updateset) && $updateset != 0)
		stderr("Error","Invalid Class");
}
$subject = trim($_POST['subject']);
$query = sql_query("SELECT id FROM users WHERE class IN (".implode(",", $updateset).")");

$amount = sqlesc(getsize_int($amount,"G"));
sql_query("UPDATE users SET uploaded=uploaded + $amount WHERE class IN (".implode(",", $updateset).")") or sqlerr(__FILE__, __LINE__);

while($dat=mysql_fetch_assoc($query))
{
	sql_query("INSERT INTO messages (sender, receiver, added,  subject, msg) VALUES ($sender_id, {$dat['id']}, $dt, " . sqlesc($subject) .", " . sqlesc($msg) .")") or sqlerr(__FILE__,__LINE__);
}

header("Refresh: 0; url=amountupload.php?sent=1");
?>
