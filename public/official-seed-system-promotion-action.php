<?php

require_once __DIR__ . '/../include/bittorrent.php';
require_once __DIR__ . '/../include/nova_sticky_promotion.php';
dbconn();
loggedinorreturn();

if (!nova_official_seed_is_manager() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    stderr('权限不足', '只有主管和管理员可以配置新官种系统助推。');
}

$userId = (int)$CURUSER['id'];
if (!nova_sticky_system_config_verify_token((string)($_POST['token'] ?? ''), $userId)) {
    stderr('请求已失效', '请刷新页面后重新提交。');
}

$enabled = ($_POST['enabled'] ?? '') === 'yes' ? 1 : 0;
$startsAt = trim((string)($_POST['starts_at'] ?? ''));
$endsAt = trim((string)($_POST['ends_at'] ?? ''));
$startTimestamp = $startsAt === '' ? null : strtotime($startsAt);
$endTimestamp = $endsAt === '' ? null : strtotime($endsAt);
if (($startsAt !== '' && !$startTimestamp) || ($endsAt !== '' && !$endTimestamp)) {
    stderr('时间无效', '请输入有效的开始或结束时间。');
}
if ($startTimestamp && $endTimestamp && $endTimestamp <= $startTimestamp) {
    stderr('时间无效', '结束时间必须晚于开始时间。');
}

$startSql = $startTimestamp ? date('Y-m-d H:i:s', $startTimestamp) : null;
$endSql = $endTimestamp ? date('Y-m-d H:i:s', $endTimestamp) : null;
sql_query("INSERT INTO nova_system_official_sticky_settings (id, enabled, starts_at, ends_at, updated_by, updated_at)
    VALUES (1, {$enabled}, " . ($startSql ? sqlesc($startSql) : 'NULL') . ', ' . ($endSql ? sqlesc($endSql) : 'NULL') . ", {$userId}, NOW())
    ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), starts_at = VALUES(starts_at), ends_at = VALUES(ends_at), updated_by = VALUES(updated_by), updated_at = NOW()") or sqlerr();

header('Location: official-seed-activity.php?system_promotion_saved=1');
exit;
