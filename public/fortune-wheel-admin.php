<?php
require_once __DIR__ . '/../include/bittorrent.php';
dbconn();
require_once __DIR__ . '/../include/nova_lucky_draw.php';

header('Content-Type: application/json; charset=utf-8');

function nova_draw_admin_response(bool $success, string $message): never
{
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$CURUSER || !nova_draw_is_admin()) {
    nova_draw_admin_response(false, '无权操作抽奖配置。');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    nova_draw_admin_response(false, '仅支持 POST 请求。');
}
$userId = (int)$CURUSER['id'];
if (!nova_draw_verify_token((string)($_POST['token'] ?? ''), $userId, 'admin')) {
    nova_draw_admin_response(false, '请求已失效，请刷新页面。');
}

$action = (string)($_POST['action'] ?? '');
$ip = getip();
try {
    if ($action === 'settings') {
        $values = [
            'enabled' => ($_POST['enabled'] ?? 'no') === 'yes' ? 'yes' : 'no',
            'cost_per_draw' => (string)max(0, round((float)($_POST['cost_per_draw'] ?? 1000), 1)),
            'daily_free_draws' => (string)max(0, min(50, (int)($_POST['daily_free_draws'] ?? 0))),
            'minimum_class' => (string)max(UC_USER, min(UC_STAFFLEADER, (int)($_POST['minimum_class'] ?? UC_USER))),
            'vip_compensation' => (string)max(0, round((float)($_POST['vip_compensation'] ?? 10000), 1)),
            'duplicate_compensation' => (string)max(0, round((float)($_POST['duplicate_compensation'] ?? 5000), 1)),
            'multi_draw_counts' => implode(',', array_values(array_filter(array_unique(array_map('intval', explode(',', (string)($_POST['multi_draw_counts'] ?? '1,10,20,50')))), static fn (int $count) => $count >= 1 && $count <= 50))),
            'homepage_winner_count' => (string)max(1, min(20, (int)($_POST['homepage_winner_count'] ?? 8))),
        ];
        if (!str_contains(',' . $values['multi_draw_counts'] . ',', ',1,')) {
            $values['multi_draw_counts'] = '1,' . $values['multi_draw_counts'];
        }
        sql_query('START TRANSACTION');
        foreach ($values as $key => $value) {
            sql_query('INSERT INTO nova_draw_settings (setting_key, setting_value, updated_by, updated_at) VALUES (' . sqlesc($key) . ', ' . sqlesc($value) . ", {$userId}, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()") or throw new RuntimeException('保存设置失败。');
        }
        sql_query("INSERT INTO nova_draw_audit_logs (operator_id, action, detail, ip, created_at) VALUES ({$userId}, 'settings', " . sqlesc(json_encode($values, JSON_UNESCAPED_UNICODE)) . ', ' . sqlesc($ip) . ', NOW())');
        sql_query('COMMIT');
        nova_draw_admin_response(true, '抽奖设置已保存。');
    }

    if ($action === 'save_prize') {
        $allowedTypes = ['bonus', 'upload', 'vip_days', 'rainbow_id_days', 'invite_temp', 'invite_perm', 'attendance_card', 'rename_card', 'medal', 'nothing'];
        $id = max(0, (int)($_POST['id'] ?? 0));
        $name = trim((string)($_POST['name'] ?? ''));
        $type = (string)($_POST['type'] ?? 'nothing');
        $value = max(0, (int)($_POST['value'] ?? 0));
        $medalId = max(0, (int)($_POST['medal_id'] ?? 0));
        $weight = max(0.001, min(100000, (float)($_POST['weight'] ?? 1)));
        $stock = max(-1, min(100000000, (int)($_POST['stock'] ?? -1)));
        $sortOrder = max(0, min(100000, (int)($_POST['sort_order'] ?? 0)));
        $enabled = ($_POST['enabled'] ?? '0') === '1' ? 1 : 0;
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_POST['color'] ?? '')) ? (string)$_POST['color'] : '#15988d';
        $description = trim((string)($_POST['description'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100 || !in_array($type, $allowedTypes, true)) {
            throw new InvalidArgumentException('奖品名称或类型无效。');
        }
        if ($type === 'medal') {
            $medalResult = sql_query("SELECT id FROM medals WHERE id = {$medalId} LIMIT 1");
            if (!$medalResult || !mysql_num_rows($medalResult)) {
                throw new InvalidArgumentException('请选择有效勋章。');
            }
        }
        if ($id > 0) {
            sql_query("UPDATE nova_draw_prizes SET name = " . sqlesc($name) . ', type = ' . sqlesc($type) . ", value = {$value}, medal_id = " . ($medalId ?: 'NULL') . ', weight = ' . sqlesc($weight) . ", stock = {$stock}, sort_order = {$sortOrder}, enabled = {$enabled}, color = " . sqlesc($color) . ', description = ' . sqlesc($description) . ", updated_at = NOW() WHERE id = {$id}") or throw new RuntimeException('更新奖品失败。');
        } else {
            sql_query('INSERT INTO nova_draw_prizes (name, type, value, medal_id, weight, stock, sort_order, enabled, color, description, created_at, updated_at) VALUES (' . sqlesc($name) . ', ' . sqlesc($type) . ", {$value}, " . ($medalId ?: 'NULL') . ', ' . sqlesc($weight) . ", {$stock}, {$sortOrder}, {$enabled}, " . sqlesc($color) . ', ' . sqlesc($description) . ', NOW(), NOW())') or throw new RuntimeException('新增奖品失败。');
            $id = mysql_insert_id();
        }
        sql_query("INSERT INTO nova_draw_audit_logs (operator_id, action, target_id, detail, ip, created_at) VALUES ({$userId}, 'save_prize', {$id}, " . sqlesc($name . ' / ' . $type) . ', ' . sqlesc($ip) . ', NOW())');
        nova_draw_admin_response(true, '奖品已保存。');
    }

    if ($action === 'delete_prize') {
        $id = max(0, (int)($_POST['id'] ?? 0));
        if ($id < 1) {
            throw new InvalidArgumentException('奖品编号无效。');
        }
        sql_query("DELETE FROM nova_draw_prizes WHERE id = {$id}") or throw new RuntimeException('删除奖品失败。');
        sql_query("INSERT INTO nova_draw_audit_logs (operator_id, action, target_id, ip, created_at) VALUES ({$userId}, 'delete_prize', {$id}, " . sqlesc($ip) . ', NOW())');
        nova_draw_admin_response(true, '奖品已删除。');
    }
    throw new InvalidArgumentException('未知操作。');
} catch (Throwable $e) {
    @sql_query('ROLLBACK');
    nova_draw_admin_response(false, $e->getMessage());
}
