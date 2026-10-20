<?php

require_once __DIR__ . '/../include/bittorrent.php';
dbconn();
loggedinorreturn();

if (!nova_official_seed_is_manager()) {
    stderr('权限不足', '只有主管和管理员可以配置活动时间。');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    stderr('请求错误', '请从官种保种统计页面提交活动时间。');
}

$userId = (int)$CURUSER['id'];
if (!nova_official_seed_verify_round_token((string)($_POST['token'] ?? ''), $userId)) {
    stderr('请求已失效', '请刷新页面后重新提交。');
}

$roundId = max(0, (int)($_POST['round_id'] ?? 0));
$startsAt = trim((string)($_POST['starts_at'] ?? ''));
$endsAt = trim((string)($_POST['ends_at'] ?? ''));
$startTimestamp = strtotime($startsAt);
$endTimestamp = strtotime($endsAt);
if (!$startTimestamp || !$endTimestamp || $endTimestamp <= $startTimestamp || $endTimestamp <= time()) {
    stderr('时间无效', '结束时间必须晚于开始时间并且晚于当前时间。');
}
$startSql = date('Y-m-d H:i:s', $startTimestamp);
$endSql = date('Y-m-d H:i:s', $endTimestamp);

$overlapSql = "SELECT id FROM nova_official_seed_rounds
    WHERE status <> 'ended' AND starts_at < " . sqlesc($endSql) . " AND ends_at > " . sqlesc($startSql)
    . ($roundId > 0 ? " AND id <> {$roundId}" : '') . ' LIMIT 1';
$overlap = sql_query($overlapSql);
if ($overlap && mysql_num_rows($overlap)) {
    stderr('时间冲突', '该时间段与另一个未结束活动重叠。');
}

$status = $startTimestamp > time() ? 'scheduled' : 'active';
if ($roundId > 0) {
    $existingResult = sql_query("SELECT * FROM nova_official_seed_rounds WHERE id = {$roundId} LIMIT 1");
    $existing = $existingResult ? mysql_fetch_assoc($existingResult) : null;
    if (!$existing || $existing['status'] === 'ended') {
        stderr('无法修改', '已结束的活动轮次不能修改。');
    }
    sql_query("UPDATE nova_official_seed_rounds SET starts_at = " . sqlesc($startSql) . ', ends_at = ' . sqlesc($endSql) . ', status = ' . sqlesc($status) . ", updated_at = NOW() WHERE id = {$roundId}") or sqlerr();
} else {
    sql_query("INSERT INTO nova_official_seed_rounds (starts_at, ends_at, status, created_by, created_at, updated_at)
        VALUES (" . sqlesc($startSql) . ', ' . sqlesc($endSql) . ', ' . sqlesc($status) . ", {$userId}, NOW(), NOW())") or sqlerr();
}

header('Location: official-seed-activity.php?saved=1');
exit;
