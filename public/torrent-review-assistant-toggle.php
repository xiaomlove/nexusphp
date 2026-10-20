<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '请求方式不正确'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (get_user_class() < UC_ADMINISTRATOR) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '您没有修改审种助手设置的权限'], JSON_UNESCAPED_UNICODE);
    exit;
}

$expectedToken = hash_hmac('sha256', 'nova-review-toggle|' . (int)$CURUSER['id'], (string)config('app.key'));
$submittedToken = (string)($_POST['token'] ?? '');
if (!hash_equals($expectedToken, $submittedToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '安全校验失败，请刷新页面后重试'], JSON_UNESCAPED_UNICODE);
    exit;
}

$enabled = (string)($_POST['enabled'] ?? '');
if (!in_array($enabled, ['yes', 'no'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => '设置值无效'], JSON_UNESCAPED_UNICODE);
    exit;
}

saveSetting('torrent', ['review_assistant_enabled' => $enabled]);
do_log(sprintf(
    'Torrent review assistant changed to %s by %s(%d)',
    $enabled,
    $CURUSER['username'],
    $CURUSER['id']
));
echo json_encode([
    'success' => true,
    'message' => $enabled === 'yes' ? '审种助手已开启' : '审种助手已关闭',
], JSON_UNESCAPED_UNICODE);

