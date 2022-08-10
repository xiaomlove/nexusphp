<?php
require "../include/bittorrent.php";
if ($_SERVER["REQUEST_METHOD"] != "POST")
	stderr("Error", "Permission denied!");
dbconn();
loggedinorreturn();

if (get_user_class() < UC_ADMINISTRATOR)
	stderr("Sorry", "Permission denied.");

$sender_id = ($_POST['sender'] == 'system' ? 0 : (int)$CURUSER['id']);
$dt = sqlesc(date("Y-m-d H:i:s"));
$msg = trim($_POST['msg']);
if (!$msg)
	stderr("Error","Don't leave any fields blank.");
$updateset = $_POST['clases'];
if (is_array($updateset)) {
	foreach ($updateset as &$class) {
        $class=intval($class);
		if (!is_valid_id($class) && $class != 0)
			stderr("Error","Invalid Class");
	}
}else{
	if (!is_valid_id($updateset) && $updateset != 0)
		stderr("Error","Invalid Class");
}
$subject = trim($_POST['subject']);
$size = 10000;
$page = 1;
set_time_limit(300);
$classStr = implode(",", $updateset);
while (true) {
    $msgValues = $idArr = [];
    $offset = ($page - 1) * $size;
    $query = sql_query("SELECT id FROM users WHERE class IN (".implode(",", $updateset).") and `enabled` = 'yes' and `status` = 'confirmed'");
    while($dat=mysql_fetch_assoc($query))
    {
        $idArr[] = $dat['id'];
        $msgValues[] = sprintf('(%s, %s, %s, %s, %s)', $sender_id, $dat['id'], $dt, sqlesc($subject), sqlesc($msg));
    }
    if (empty($idArr)) {
        break;
    }
    $idStr = implode(', ', $idArr);
    $sql = "INSERT INTO messages (sender, receiver, added,  subject, msg) VALUES " . implode(', ', $msgValues);
    sql_query($sql);
    $page++;
}

header("Refresh: 0; url=staffmess.php?sent=1");
?>
