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
$conditions = [];
if (!empty($_POST['classes'])) {
    $conditions[] = "class IN (" . implode(', ', $_POST['classes']) . ")";
}
$conditions = apply_filter("role_query_conditions", $conditions, $_POST);
if (empty($conditions)) {
    stderr("Error","No valid filter");
}
$whereStr = implode(' OR ', $conditions);
while (true) {
    $msgValues = [];
    $offset = ($page - 1) * $size;
    $query = sql_query("SELECT id FROM users WHERE ($whereStr) and `enabled` = 'yes' and `status` = 'confirmed' limit $offset, $size");
    while($dat=mysql_fetch_assoc($query))
    {
        $msgValues[] = sprintf('(%s, %s, %s, %s, %s)', $sender_id, $dat['id'], $dt, sqlesc($subject), sqlesc($msg));
    }
    if (empty($msgValues)) {
        break;
    }
    $sql = "INSERT INTO messages (sender, receiver, added,  subject, msg) VALUES " . implode(', ', $msgValues);
    sql_query($sql);
    $page++;
}

header("Refresh: 0; url=staffmess.php?sent=1");
?>
