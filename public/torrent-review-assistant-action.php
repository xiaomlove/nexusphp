<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '请求方式不正确'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = (string)($_POST['action'] ?? '');
$canReview = user_can('torrent-approval');
$canForceApprove = get_user_class() >= UC_ADMINISTRATOR;
if ($action === 'notify_correction' && !$canReview) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '您没有发送审种整改通知的权限'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'force_approve_batch' && !$canForceApprove) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '只有主管和管理员可以批量强制通过'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!in_array($action, ['notify_correction', 'force_approve_batch'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => '操作类型无效'], JSON_UNESCAPED_UNICODE);
    exit;
}

$expectedToken = hash_hmac('sha256', 'nova-review-action|' . (int)$CURUSER['id'], (string)config('app.key'));
if (!hash_equals($expectedToken, (string)($_POST['token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '安全校验失败，请刷新页面后重试'], JSON_UNESCAPED_UNICODE);
    exit;
}

$operator = \App\Models\User::query()->findOrFail($CURUSER['id']);
$repository = new \App\Repositories\TorrentRepository();

try {
    if ($action === 'notify_correction') {
        $torrentId = (int)($_POST['torrent_id'] ?? 0);
        $issues = trim((string)($_POST['issues'] ?? ''));
        if ($torrentId < 1 || $issues === '') {
            throw new \InvalidArgumentException('缺少种子或整改问题');
        }
        $issues = mb_substr($issues, 0, 8000, 'UTF-8');
        $torrent = \App\Models\Torrent::query()->findOrFail($torrentId, ['id', 'approval_status']);
        if ($torrent->approval_status == \App\Models\Torrent::APPROVAL_STATUS_DENY) {
            throw new \InvalidArgumentException('该资源已处于审核不通过状态，请勿重复发送');
        }
        $repository->approval($operator, [
            'torrent_id' => $torrentId,
            'approval_status' => \App\Models\Torrent::APPROVAL_STATUS_DENY,
            'comment' => "审种助手整改通知：\n" . $issues,
        ]);
        do_log(sprintf('Torrent review correction notice sent: torrent_id=%d, operator=%s(%d)', $torrentId, $CURUSER['username'], $CURUSER['id']));
        echo json_encode(['success' => true, 'message' => '已标记为需要修正，并通过站内信通知发布员'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $torrentIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['torrent_ids'] ?? [])))));
    if (!$torrentIds || count($torrentIds) > 50) {
        throw new \InvalidArgumentException('请选择1至50个待审核种子');
    }
    $pendingIds = \App\Models\Torrent::query()
        ->whereIn('id', $torrentIds)
        ->where('approval_status', \App\Models\Torrent::APPROVAL_STATUS_NONE)
        ->pluck('id')
        ->all();
    if (!$pendingIds) {
        throw new \InvalidArgumentException('所选种子均已完成审核');
    }
    foreach ($pendingIds as $torrentId) {
        $repository->approval($operator, [
            'torrent_id' => $torrentId,
            'approval_status' => \App\Models\Torrent::APPROVAL_STATUS_ALLOW,
            'comment' => '主管或管理员忽略审种助手错误并批量通过',
        ]);
    }
    do_log(sprintf(
        'Torrent review force approved in batch: torrent_ids=%s, operator=%s(%d)',
        implode(',', $pendingIds),
        $CURUSER['username'],
        $CURUSER['id']
    ));
    echo json_encode([
        'success' => true,
        'message' => '已批量通过 ' . count($pendingIds) . ' 个待审核种子',
        'affected' => count($pendingIds),
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}

