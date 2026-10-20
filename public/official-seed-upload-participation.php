<?php

require_once __DIR__ . '/../include/bittorrent.php';
dbconn();
loggedinorreturn();

$isManager = nova_official_seed_is_manager();
$currentUserId = (int)$CURUSER['id'];
$requestedUserId = (int)($_GET['user_id'] ?? 0);
$userId = $isManager && $requestedUserId > 0 ? $requestedUserId : $currentUserId;

function nova_upload_participation_rows(string $sql): array {
    $result = sql_query($sql);
    $rows = [];
    while ($result && ($row = mysql_fetch_assoc($result))) {
        $rows[] = $row;
    }
    return $rows;
}

$user = mysql_fetch_assoc(sql_query('SELECT id, username FROM users WHERE id = ' . $userId . ' LIMIT 1'));
if (!$user) {
    stderr('Error', 'User not found.');
}

$rows = nova_upload_participation_rows('SELECT p.*, t.name, c.status AS campaign_status, GREATEST(COALESCE(s.seedtime, p.baseline_seedtime) - p.baseline_seedtime, p.seed_seconds, 0) AS live_seed_seconds, EXISTS(SELECT 1 FROM peers px WHERE px.torrent = p.torrent_id AND px.userid = p.user_id AND px.seeder = \'yes\') AS is_seeding FROM nova_official_seed_participants p JOIN nova_official_seed_campaigns c ON c.id = p.campaign_id JOIN torrents t ON t.id = p.torrent_id LEFT JOIN snatched s ON s.torrentid = p.torrent_id AND s.userid = p.user_id WHERE p.user_id = ' . $userId . ' ORDER BY c.status = \'active\' DESC, live_seed_seconds DESC, p.id DESC LIMIT 500');

stdhead('Upload reward participation');
?>
<style>
.nova-upd{width:min(1380px,calc(100% - 24px));margin:18px auto 32px;color:#25484b}.nova-upd *{box-sizing:border-box}.nova-upd-hero{padding:19px 22px;border-radius:8px;background:linear-gradient(105deg,#0b4350,#087a70);color:#fff}.nova-upd-hero h1{margin:0!important;color:#fff!important;font-size:21px!important}.nova-upd-hero p{margin:7px 0 0;color:#d8fbf7}.nova-upd-panel{margin-top:12px;overflow:hidden;border:1px solid #cfe4e1;border-radius:8px;background:#fff}.nova-upd-scroll{overflow:auto}.nova-upd-table{width:100%;border-collapse:collapse}.nova-upd-table th,.nova-upd-table td{padding:11px 10px;border-bottom:1px solid #e4efed;text-align:center;white-space:nowrap}.nova-upd-table th{background:#f8fcfb;color:#45656a}.nova-upd-table td.name{min-width:360px;text-align:left;white-space:normal}.nova-upd-table a{color:#c93345!important;font-weight:700}.nova-upd-reward{color:#ba6a12;font-weight:750}.nova-upd-back{display:inline-flex;margin-top:12px;padding:8px 13px;border-radius:5px;background:#0f766e;color:#fff!important;text-decoration:none}@media(max-width:800px){.nova-upd{width:calc(100% - 16px)}.nova-upd-hero{padding:16px}.nova-upd-table th,.nova-upd-table td{padding:9px 8px}}
</style>
<main class="nova-upd"><header class="nova-upd-hero"><h1>&#19978;&#20256;&#37327;&#22870;&#21169;&#21442;&#19982;&#36164;&#28304;</h1><p><?= htmlspecialchars($user['username']) ?> (#<?= (int)$user['id'] ?>) &#30340;&#20445;&#31181;&#21442;&#19982;&#35760;&#24405;</p></header><section class="nova-upd-panel"><div class="nova-upd-scroll"><table class="nova-upd-table"><thead><tr><th>&#36164;&#28304;</th><th>&#29366;&#24577;</th><th>&#23454;&#38469;&#20445;&#31181;</th><th>15 &#22825;&#22870;&#21169;</th><th>30 &#22825;&#32047;&#35745;&#22870;&#21169;</th></tr></thead><tbody><?php foreach($rows as $row): $seconds = (int)$row['live_seed_seconds']; $reward15 = $row['reward_15_at'] ? mksize((int)$row['reward_15_bytes']) : ($seconds >= 15 * 86400 ? '&#24453;&#32467;&#31639;' : '&#26410;&#36798;&#21040;'); $reward30 = $row['reward_30_at'] ? mksize((int)$row['reward_15_bytes'] + (int)$row['reward_30_bytes']) : ($seconds >= 30 * 86400 ? '&#24453;&#32467;&#31639;' : '&#26410;&#36798;&#21040;'); ?><tr><td class="name"><a href="details.php?id=<?= (int)$row['torrent_id'] ?>"><?= htmlspecialchars($row['name']) ?></a></td><td><?= (int)$row['is_seeding'] ? '&#27491;&#22312;&#20445;&#31181;' : '&#24403;&#21069;&#26410;&#36830;&#25509;' ?></td><td><?= htmlspecialchars(nova_official_seed_duration($seconds)) ?></td><td class="nova-upd-reward"><?= $reward15 ?></td><td class="nova-upd-reward"><?= $reward30 ?></td></tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="5">&#26242;&#26080;&#19978;&#20256;&#37327;&#22870;&#21169;&#21442;&#19982;&#35760;&#24405;</td></tr><?php endif; ?></tbody></table></div></section><a class="nova-upd-back" href="official-seed-rewards.php<?= $isManager ? '?user_id=' . $userId : '' ?>">&#36820;&#22238;&#27963;&#21160;&#22870;&#21169;</a></main>
<?php stdfoot();
