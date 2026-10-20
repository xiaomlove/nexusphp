<?php
ob_start();
require_once('../include/bittorrent.php');
require_once('../include/nova_sticky_promotion.php');
dbconn();
loggedinorreturn();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    stderr('操作失败', '仅支持提交操作。');
}

$torrentId = intval($_POST['torrent_id'] ?? 0);
$action = (string)($_POST['action'] ?? '');
$userId = intval($CURUSER['id'] ?? 0);
$token = (string)($_POST['token'] ?? '');
if (!$torrentId || !in_array($action, ['start', 'append', 'privacy'], true) || !nova_sticky_verify_token($token, $torrentId, $action, $userId)) {
    stderr('操作失败', '请求验证失败，请刷新页面后重试。');
}

try {
    if ($action === 'start') {
        nova_sticky_start($torrentId, $userId, $_POST);
    } elseif ($action === 'append') {
        nova_sticky_append($torrentId, $userId, $_POST);
    } else {
        nova_sticky_set_anonymous($torrentId, $userId, ($_POST['anonymous'] ?? 'no') === 'yes');
    }
    header('Location: details.php?id=' . $torrentId . '&nova_sticky_success=1#nova-sticky-panel');
    exit;
} catch (Throwable $e) {
    stderr('助推操作失败', htmlspecialchars($e->getMessage()));
}
