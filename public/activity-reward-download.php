<?php
require_once '../include/bittorrent.php';
dbconn();
loggedinorreturn();
parked();

$type = $_GET['type'] ?? '';
if (!in_array($type, ['magic', 'upload'], true)) {
    httperr();
}
if (($CURUSER['downloadpos'] ?? 'no') === 'no') {
    permissiondenied();
}
if (!class_exists('ZipArchive')) {
    stderr('错误', '服务器未启用 ZIP 扩展，暂时无法批量下载。');
}

@set_time_limit(0);
@ini_set('memory_limit', '512M');
ignore_user_abort(true);

$condition = nova_official_seed_reward_condition($type, 't');
$approvalFilter = '';
if (get_setting('torrent.approval_status_none_visible') === 'no' && !user_can('staffmem')) {
    $approvalFilter = ' AND t.approval_status = ' . (int)\App\Models\Torrent::APPROVAL_STATUS_ALLOW;
}
$result = sql_query("SELECT t.id, t.name, t.save_as, t.owner, t.banned, t.approval_status,
        categories.mode AS search_box_id
    FROM torrents t
    LEFT JOIN categories ON categories.id = t.category
    WHERE {$condition} AND t.visible = 'yes' {$approvalFilter}
    ORDER BY t.id DESC");

$tempFile = tempnam(sys_get_temp_dir(), 'nova-reward-');
if ($tempFile === false) {
    stderr('错误', '无法创建临时下载文件，请稍后重试。');
}
$zipFile = $tempFile . '.zip';
@unlink($tempFile);
$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    stderr('错误', '无法创建批量下载包，请稍后重试。');
}

$added = 0;
$tracker = get_tracker_schema_and_host($CURUSER['tracker_url_id'], true) . '?passkey=' . $CURUSER['passkey'];
while ($row = mysql_fetch_assoc($result)) {
    $approvalDenied = $row['approval_status'] != \App\Models\Torrent::APPROVAL_STATUS_ALLOW
        && get_setting('torrent.approval_status_none_visible') === 'no'
        && (int)$row['owner'] !== (int)$CURUSER['id'];
    if (($row['banned'] === 'yes' || $approvalDenied) && !user_can('seebanned')) {
        continue;
    }
    if (!can_access_torrent($row, (int)$CURUSER['id'])) {
        continue;
    }
    $source = getFullDirectory("{$torrent_dir}/{$row['id']}.torrent");
    if (!is_file($source) || !is_readable($source) || filesize($source) === 0) {
        continue;
    }
    try {
        $torrent = \Rhilip\Bencode\Bencode::load($source);
        $torrent['announce'] = $tracker;
        $torrent['comment'] = getSchemeAndHttpHost(true) . '/details.php?id=' . (int)$row['id'];
        $safeName = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', '_', (string)($row['save_as'] ?: $row['name']));
        $safeName = trim((string)$safeName, " .\t\r\n");
        if ($safeName === '') {
            $safeName = 'torrent';
        }
        $entryName = sprintf('%06d-%s.torrent', (int)$row['id'], mb_strcut($safeName, 0, 150, 'UTF-8'));
        if ($zip->addFromString($entryName, \Rhilip\Bencode\Bencode::encode($torrent))) {
            $added++;
        }
    } catch (Throwable $e) {
        do_log('[ACTIVITY_REWARD_BATCH_SKIP] torrent=' . (int)$row['id'] . ' error=' . $e->getMessage(), 'error');
    }
}
$zip->close();

if ($added === 0 || !is_file($zipFile)) {
    @unlink($zipFile);
    stderr('暂无资源', '当前没有可下载的' . nova_official_seed_reward_label($type) . '种子。');
}

register_shutdown_function(static function () use ($zipFile): void {
    @unlink($zipFile);
});
$fileName = 'NovaHD-' . ($type === 'upload' ? 'upload-reward' : 'magic-reward') . '-' . date('Ymd-His') . '.zip';
header('Content-Type: application/zip');
header('Content-Length: ' . filesize($zipFile));
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($zipFile);
exit;
