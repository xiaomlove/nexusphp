<?php
ob_start();
require_once('../include/bittorrent.php');
dbconn();
loggedinorreturn();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    stderr('操作失败', '仅支持提交操作。');
}
if (!nova_permission_can_manage()) {
    permissiondenied();
}

$operatorId = intval($CURUSER['id']);
$torrentId = intval($_POST['torrent_id'] ?? 0);
$action = (string)($_POST['action'] ?? '');
$token = (string)($_POST['token'] ?? '');
$allowed = ['save_role', 'delete_role', 'assign_role', 'revoke_role', 'grant_direct', 'revoke_direct'];
if (!$torrentId || !in_array($action, $allowed, true) || !nova_permission_verify_token($token, $operatorId)) {
    stderr('操作失败', '请求验证失败，请刷新页面后重试。');
}

$redirectUid = 0;
try {
    if ($action === 'save_role') {
        $permissions = nova_permission_normalize((array)($_POST['permissions'] ?? []), (string)($_POST['custom_permissions'] ?? ''));
        nova_permission_save_role($operatorId, (string)($_POST['name'] ?? ''), (string)($_POST['description'] ?? ''), (string)($_POST['color'] ?? ''), $permissions);
    } elseif ($action === 'delete_role') {
        nova_permission_delete_role($operatorId, intval($_POST['role_id'] ?? 0));
    } elseif ($action === 'assign_role') {
        $user = nova_permission_find_user((string)($_POST['username'] ?? ''));
        $redirectUid = (int)$user['id'];
        nova_permission_assign_role($operatorId, $redirectUid, intval($_POST['role_id'] ?? 0), max(0, intval($_POST['days'] ?? 0)));
    } elseif ($action === 'revoke_role') {
        $redirectUid = nova_permission_revoke_role($operatorId, intval($_POST['assignment_id'] ?? 0));
    } elseif ($action === 'grant_direct') {
        $user = nova_permission_find_user((string)($_POST['username'] ?? ''));
        $redirectUid = (int)$user['id'];
        $permissions = nova_permission_normalize((array)($_POST['permissions'] ?? []), (string)($_POST['custom_permissions'] ?? ''));
        nova_permission_grant_direct($operatorId, $redirectUid, $permissions, max(0, intval($_POST['days'] ?? 0)));
    } else {
        $redirectUid = nova_permission_revoke_direct($operatorId, intval($_POST['grant_id'] ?? 0));
    }
    $url = 'details.php?id=' . $torrentId . '&nova_permission_success=1';
    if ($redirectUid > 0) {
        $url .= '&permission_uid=' . $redirectUid;
    }
    header('Location: ' . $url . '#nova-permission-panel');
    exit;
} catch (Throwable $e) {
    stderr('权限操作失败', htmlspecialchars($e->getMessage()));
}

