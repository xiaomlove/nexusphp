<?php

require_once('../include/bittorrent.php');
require_once('../include/iyuu_torrent_match.php');
dbconn();
loggedinorreturn();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

function nova_iyuu_json_response(array $data, int $status = 200): void
{
    if (!empty($data['sites']) && is_array($data['sites'])) {
        foreach ($data['sites'] as $idx => $site) {
            if (!is_array($site)) {
                continue;
            }
            $host = (string) parse_url((string) ($site['url'] ?? ''), PHP_URL_HOST);
            if ($host === '') {
                continue;
            }
            $safe = preg_replace('/[^a-z0-9.-]/i', '_', strtolower($host));
            $files = glob(__DIR__ . '/pic/site-icons/' . $safe . '.*') ?: [];
            $chosen = '';
            foreach ($files as $candidate) {
                if (preg_match('/\.(png|jpe?g|gif|webp|ico|svg)$/i', $candidate)) {
                    $chosen = basename($candidate);
                    if (!preg_match('/\.svg$/i', $candidate)) {
                        break;
                    }
                }
            }
            $data['sites'][$idx]['icon'] = '/pic/site-icons/' . ($chosen !== '' ? $chosen : 'default.svg');
        }
    }
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    nova_iyuu_json_response(['ok' => false, 'message' => '种子参数无效'], 400);
}

$result = sql_query("SELECT * FROM torrents WHERE id = $id LIMIT 1");
$torrent = mysql_fetch_assoc($result);
if (!$torrent) {
    nova_iyuu_json_response(['ok' => false, 'message' => '种子不存在'], 404);
}
if (($torrent['banned'] ?? 'no') === 'yes' && !user_can('seebanned') && (int)$torrent['owner'] !== (int)$CURUSER['id']) {
    nova_iyuu_json_response(['ok' => false, 'message' => '没有访问权限'], 403);
}
if (!can_access_torrent($torrent, $CURUSER['id']) && (int)$torrent['owner'] !== (int)$CURUSER['id']) {
    nova_iyuu_json_response(['ok' => false, 'message' => '没有访问权限'], 403);
}

try {
    $matches = nova_iyuu_find_torrent_matches((string)$torrent['info_hash']);
    nova_iyuu_json_response(['ok' => true] + $matches);
} catch (Throwable $exception) {
    do_log(sprintf('IYUU match failed for torrent %d: %s', $id, $exception->getMessage()));
    nova_iyuu_json_response(['ok' => false, 'message' => '其他站点查询暂时不可用'], 503);
}
