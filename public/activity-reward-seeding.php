<?php

require_once __DIR__ . '/../include/bittorrent.php';
dbconn();
loggedinorreturn();

$userId = (int)($_GET['user_id'] ?? 0);
$source = $_GET['source'] ?? '';
$viewerId = (int)$CURUSER['id'];
if ($userId <= 0 || !in_array($source, ['magic', 'upload', 'boost'], true)) {
    http_response_code(400);
    exit('<div class="ar-seed-empty">参数无效。</div>');
}
if (!nova_official_seed_is_manager() && $userId !== $viewerId) {
    http_response_code(403);
    exit('<div class="ar-seed-empty">无权查看该用户的资源。</div>');
}

$torrentIds = [];
if ($source === 'magic') {
    $result = sql_query("SELECT torrent_ids FROM nova_official_seed_magic_reward_events WHERE user_id={$userId}");
    while ($result && ($row = mysql_fetch_assoc($result))) {
        foreach (preg_split('/[^0-9]+/', (string)$row['torrent_ids'], -1, PREG_SPLIT_NO_EMPTY) as $torrentId) {
            $torrentIds[(int)$torrentId] = true;
        }
    }
} elseif ($source === 'upload') {
    $result = sql_query("SELECT DISTINCT torrent_id FROM nova_official_seed_participants WHERE user_id={$userId}");
    while ($result && ($row = mysql_fetch_assoc($result))) {
        $torrentIds[(int)$row['torrent_id']] = true;
    }
} else {
    $result = sql_query("SELECT DISTINCT c.torrent_id
        FROM nova_sticky_promotion_participants p
        JOIN nova_sticky_promotions c ON c.id=p.campaign_id
        WHERE c.sponsor_id=0 AND p.user_id={$userId} AND p.reward>0");
    while ($result && ($row = mysql_fetch_assoc($result))) {
        $torrentIds[(int)$row['torrent_id']] = true;
    }
}

$torrentIds = array_values(array_filter(array_map('intval', array_keys($torrentIds))));
if (!$torrentIds) {
    exit('<div class="ar-seed-empty">暂无对应活动资源。</div>');
}
$idList = implode(',', $torrentIds);
$rows = [];
$result = sql_query("SELECT DISTINCT t.id,t.name
    FROM torrents t
    JOIN peers p ON p.torrent=t.id AND p.userid={$userId} AND p.seeder='yes'
    WHERE t.id IN ({$idList})
    ORDER BY t.id DESC");
while ($result && ($row = mysql_fetch_assoc($result))) {
    $rows[] = $row;
}
if (!$rows) {
    exit('<div class="ar-seed-empty">当前没有正在做种的对应资源。</div>');
}
?>
<ul class="ar-seed-list">
<?php foreach ($rows as $row): ?>
  <li><a href="details.php?id=<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['name']) ?></a></li>
<?php endforeach; ?>
</ul>
