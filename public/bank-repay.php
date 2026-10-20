<?php
/**
 * 还款处理
 */

require_once(dirname(__FILE__) . '/../include/bittorrent.php');
dbconn();

// 检查用户是否登录
if (!$CURUSER) {
    $_SESSION['bank_message'] = ['type' => 'error', 'text' => '请先登录'];
    header('Location: /bank.php');
    exit;
}

try {
    $controller = new \NexusPlugin\BankSystem\BankController();
    $controller->repayLoan(); // 内部会回跳
} catch (\Exception $e) {
    $_SESSION['bank_message'] = ['type' => 'error', 'text' => '系统错误：' . $e->getMessage()];
    header('Location: /bank.php');
}
?>
