<?php

use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    stderr('Error', 'Permission denied!');
}
dbconn();
loggedinorreturn();

if (get_user_class() < UC_ADMINISTRATOR) {
    stderr('Sorry', 'Permission denied.');
}

$sender_id = ($_POST['sender'] == 'system' ? 0 : (int) $CURUSER['id']);
$dt = date('Y-m-d H:i:s');
$msg = trim($_POST['msg']);
if (! $msg) {
    stderr('Error', "Don't leave any fields blank.");
}
$updateset = $_POST['clases'];
if (is_array($updateset)) {
    foreach ($updateset as &$class) {
        $class = intval($class);
        if (! is_valid_id($class) && $class != 0) {
            stderr('Error', 'Invalid Class');
        }
    }
} else {
    if (! is_valid_id($updateset) && $updateset != 0) {
        stderr('Error', 'Invalid Class');
    }
}
$subject = trim($_POST['subject']);
$size = 10000;
$page = 1;
set_time_limit(300);
$conditions = [];
if (! empty($_POST['classes'])) {
    $conditions[] = 'class IN ('.implode(', ', $_POST['classes']).')';
}
$conditions = apply_filter('role_query_conditions', $conditions, $_POST);
if (empty($conditions)) {
    stderr('Error', 'No valid filter');
}
$whereStr = implode(' OR ', $conditions);
while (true) {
    $rows = [];
    $offset = ($page - 1) * $size;
    foreach (NexusDB::select("SELECT id FROM users WHERE ($whereStr) and `enabled` = 'yes' and `status` = 'confirmed' limit $offset, $size") as $dat) {
        $rows[] = [
            'sender' => $sender_id,
            'receiver' => (int) $dat['id'],
            'added' => $dt,
            'subject' => (string) $subject,
            'msg' => (string) $msg,
        ];
    }
    if (empty($rows)) {
        break;
    }
    NexusDB::table('messages')->insert($rows);
    $page++;
}

header('Location: staffmess.php?sent=1');
