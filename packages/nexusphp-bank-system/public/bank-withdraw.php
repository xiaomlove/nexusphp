<?php
/**
 * 提前支取存款处理
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
    if (!empty($_POST['demand_withdraw'])) {
        $amount = floatval($_POST['amount'] ?? 0);
        $repo = new \NexusPlugin\BankSystem\BankSystemRepository();
        if ($amount <= 0) {
            $_SESSION['bank_message'] = ['type' => 'error', 'text' => '金额必须大于0'];
            header('Location: /bank.php');
            exit;
        }
        $result = $repo->withdrawDemand($CURUSER['id'], $amount);
        if ($result['success']) {
            $_SESSION['bank_message'] = ['type' => 'success', 'text' => $result['message']];
        } else {
            $_SESSION['bank_message'] = ['type' => 'error', 'text' => $result['message']];
        }
        header('Location: /bank.php');
        exit;
    } else {
        $controller = new \NexusPlugin\BankSystem\BankController();
        $controller->withdrawDeposit();
    }
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => '系统错误：' . $e->getMessage()]);
}
?>
