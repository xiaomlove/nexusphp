<?php
require "../include/bittorrent.php";
if ($_SERVER["REQUEST_METHOD"] != "POST")
    stderr("Error", "Permission denied!");
dbconn();
loggedinorreturn();

if (get_user_class() < UC_SYSOP)
    stderr("Sorry", "Permission denied.");

$validTypeMap = [
    'seedbonus' => 'Bonus',
    'attendance_card' => 'Attend card',
    'invites' => 'Invite',
    'uploaded' => 'Upload',
];
$sender_id = ($_POST['sender'] == 'system' ? 0 : (int)$CURUSER['id']);
$dt = sqlesc(date("Y-m-d H:i:s"));
$msg = trim($_POST['msg']);
$amount = $_POST['amount'];
$type = $_POST['type'] ?? '';
if (!$msg || !$amount || !$type)
    stderr("Error","Don't leave any fields blank.");
if(!is_numeric($amount))
    stderr("Error","amount must be numeric");
if (!isset($validTypeMap[$type])) {
    stderr("Error","Invalid type");
}
if ($type == 'uploaded') {
    $amount = sqlesc(getsize_int($amount,"G"));
}
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
$size = 10000;
$page = 1;
set_time_limit(300);
$classStr = implode(",", $updateset);
while (true) {
    $msgValues = $idArr = [];
    $offset = ($page - 1) * $size;
    $query = sql_query("SELECT id FROM users WHERE class IN ($classStr) and `enabled` = 'yes' and `status` = 'confirmed' limit $offset, $size");
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
    sql_query("UPDATE users SET $type = $type + $amount WHERE id in ($idStr)");
    $page++;
}

header("Refresh: 0; url=increment-bulk.php?sent=1&type=$type");
?>
