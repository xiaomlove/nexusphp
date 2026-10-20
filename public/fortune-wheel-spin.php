<?php
require_once __DIR__ . '/../include/bittorrent.php';
dbconn();
require_once __DIR__ . '/../include/nova_lucky_draw.php';

header('Content-Type: application/json; charset=utf-8');

if (!$CURUSER) {
    echo json_encode(['success' => false, 'message' => '请先登录。'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '仅支持 POST 请求。'], JSON_UNESCAPED_UNICODE);
    exit;
}
$userId = (int)$CURUSER['id'];
if (!nova_draw_verify_token((string)($_POST['token'] ?? ''), $userId, 'spin')) {
    echo json_encode(['success' => false, 'message' => '请求已失效，请刷新页面。'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = nova_draw_spin($userId, (int)($_POST['count'] ?? 1), getip());
    echo json_encode(['success' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
