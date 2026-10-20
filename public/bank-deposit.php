<?php
/**
 * 存款处理
 */

require_once(dirname(__FILE__) . '/../include/bittorrent.php');
dbconn();

// 响应JSON
header('Content-Type: application/json; charset=utf-8');

// 检查用户是否登录
if (!$CURUSER) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

try {
    $controller = new \NexusPlugin\BankSystem\BankController();
    $controller->createDeposit();
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => '系统错误：' . $e->getMessage()]);
}
?>
