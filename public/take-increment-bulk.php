<?php
require "../include/bittorrent.php";
if ($_SERVER["REQUEST_METHOD"] != "POST")
    stderr("Error", "Permission denied!");
dbconn();
require_once(get_langfile_path('increment-bulk.php'));
loggedinorreturn();

if (get_user_class() < UC_SYSOP)
    stderr("Sorry", "Permission denied.");

$validTypeMap = $lang_increment_bulk['types'];
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
$isTypeTmpInvite = $type == 'tmp_invites';
$subject = trim($_POST['subject']);
$duration = 0;
$size = 2000;
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
if ($isTypeTmpInvite) {
    $duration = intval($_POST['duration'] ?? 0);
    if ($duration <= 0) {
        stderr("Sorry", "Invalid duration: $duration");
    }
}
$whereStr = implode(' OR ', $conditions);
while (true) {
    $msgValues = $idArr = [];
    $offset = ($page - 1) * $size;
    $query = sql_query("SELECT id FROM users WHERE ($whereStr) and `enabled` = 'yes' and `status` = 'confirmed' limit $offset, $size");
    while($dat=mysql_fetch_assoc($query))
    {
        $idArr[] = $dat['id'];
        $msgValues[] = sprintf('(%s, %s, %s, %s, %s)', $sender_id, $dat['id'], $dt, sqlesc($subject), sqlesc($msg));
    }
    if (empty($idArr)) {
        break;
    }
    $idStr = implode(',', $idArr);
    $idRedisKey = sprintf("temporary_invite:%s", microtime(true));
    \Nexus\Database\NexusDB::cache_put($idRedisKey, $idStr);
    if ($isTypeTmpInvite) {
        $command = sprintf(
            'invite:tmp %s %s %s',
            $idRedisKey, $duration, $amount
        );
        $output = executeCommand($command, 'string', true);
        do_log(sprintf('command: %s, output: %s', $command, $output));
    } else {
        sql_query("UPDATE users SET $type = $type + $amount WHERE id in ($idStr)");
    }
    $sql = "INSERT INTO messages (sender, receiver, added,  subject, msg) VALUES " . implode(', ', $msgValues);
    sql_query($sql);
    $page++;
}

header("Refresh: 0; url=increment-bulk.php?sent=1&type=$type");
?>
