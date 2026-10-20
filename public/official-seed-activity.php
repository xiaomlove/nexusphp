<?php

require_once __DIR__ . '/../include/bittorrent.php';
dbconn();
loggedinorreturn();

if (!nova_official_seed_is_manager()) {
    stdhead('官种上传量活动');
    $personalPanel = nova_official_seed_render_personal_panel((int)$CURUSER['id']);
    ?>
    <style>
      .nova-osa-public{box-sizing:border-box;width:min(1380px,calc(100% - 24px));margin:18px auto 32px}.nova-osa-public *{box-sizing:border-box}
      .nova-osa-public-head{padding:20px 24px;border-radius:8px;background:linear-gradient(100deg,#0c2d39,#137f72);color:#fff;box-shadow:0 10px 26px rgba(20,65,68,.16)}.nova-osa-public-head h1{margin:0!important;color:#fff!important;font-size:22px!important}.nova-osa-public-head p{margin:7px 0 0;color:#d8fffa;line-height:1.8}
      .nova-osa-public-empty{margin-top:12px;padding:24px;border:1px solid #d3e6e3;border-radius:8px;background:#fff;color:#60777a;text-align:center}
    </style>
    <main class="nova-osa-public"><header class="nova-osa-public-head"><h1>官种上传量活动</h1><p>官方种子发布满7天且保种人数低于3时自动参加。连续保种满15天奖励种子体积20%的上传量，满30天累计奖励50%；断开超过48小时重新计时。</p></header>
      <?= $personalPanel ?: '<div class="nova-osa-public-empty">您当前没有官种上传量活动的保种记录。</div>' ?>
    </main>
    <?php
    stdfoot();
    exit;
}

$torrentId = max(0, (int)($_GET['torrent_id'] ?? 0));
$userId = max(0, (int)($_GET['user_id'] ?? 0));
$where = ['1=1'];
if ($torrentId > 0) {
    $where[] = 'p.torrent_id = ' . $torrentId;
}
if ($userId > 0) {
    $where[] = 'p.user_id = ' . $userId;
}
$whereSql = implode(' AND ', $where);

$rounds = [];
$roundResult = sql_query("SELECT r.*, u.username AS creator,
        (SELECT COUNT(*) FROM nova_official_seed_campaigns c WHERE c.round_id = r.id) AS campaign_count
    FROM nova_official_seed_rounds r LEFT JOIN users u ON u.id = r.created_by
    ORDER BY r.id DESC LIMIT 20");
while ($roundResult && ($round = mysql_fetch_assoc($roundResult))) {
    $rounds[] = $round;
}
$editableRound = null;
foreach ($rounds as $round) {
    if (in_array($round['status'], ['active', 'scheduled'], true)) {
        $editableRound = $round;
        break;
    }
}
$roundToken = nova_official_seed_round_token((int)$CURUSER['id']);

$summaryResult = sql_query("SELECT
    (SELECT COUNT(*) FROM nova_official_seed_campaigns c JOIN nova_official_seed_rounds r ON r.id = c.round_id WHERE c.status = 'active' AND r.status = 'active') AS active_campaigns,
    (SELECT COUNT(*) FROM nova_official_seed_participants) AS participants,
    (SELECT COUNT(*) FROM nova_official_seed_participants WHERE reward_15_at IS NOT NULL) AS tier15,
    (SELECT COUNT(*) FROM nova_official_seed_participants WHERE reward_30_at IS NOT NULL) AS tier30,
    (SELECT COALESCE(SUM(reward_bytes), 0) FROM nova_official_seed_reward_events) AS reward_bytes");
$summary = mysql_fetch_assoc($summaryResult);

$campaigns = [];
$campaignResult = sql_query("SELECT c.*, t.name, t.seeders, t.added, r.starts_at AS round_starts_at, r.ends_at AS round_ends_at, r.status AS round_status,
        COUNT(p.id) AS participant_count,
        SUM(CASE WHEN p.reward_15_at IS NOT NULL THEN 1 ELSE 0 END) AS tier15_count,
        SUM(CASE WHEN p.reward_30_at IS NOT NULL THEN 1 ELSE 0 END) AS tier30_count,
        COALESCE(SUM(p.reward_15_bytes + p.reward_30_bytes), 0) AS reward_bytes
    FROM nova_official_seed_campaigns c
    JOIN nova_official_seed_rounds r ON r.id = c.round_id
    LEFT JOIN torrents t ON t.id = c.torrent_id
    LEFT JOIN nova_official_seed_participants p ON p.campaign_id = c.id
    GROUP BY c.id, t.name, t.seeders, t.added, r.starts_at, r.ends_at, r.status
    ORDER BY r.id DESC, c.status = 'active' DESC, c.qualified_at DESC
    LIMIT 300");
while ($campaignResult && ($row = mysql_fetch_assoc($campaignResult))) {
    $campaigns[] = $row;
}

$participants = [];
$participantResult = sql_query("SELECT p.*, c.status AS campaign_status, c.size_bytes, t.name, u.username,
        GREATEST(COALESCE(s.seedtime, p.baseline_seedtime) - p.baseline_seedtime, p.seed_seconds, 0) AS live_seed_seconds,
        EXISTS(SELECT 1 FROM peers px WHERE px.torrent = p.torrent_id AND px.userid = p.user_id AND px.seeder = 'yes') AS is_seeding
    FROM nova_official_seed_participants p
    JOIN nova_official_seed_campaigns c ON c.id = p.campaign_id
    JOIN torrents t ON t.id = p.torrent_id
    JOIN users u ON u.id = p.user_id
    LEFT JOIN snatched s ON s.torrentid = p.torrent_id AND s.userid = p.user_id
    WHERE {$whereSql}
    ORDER BY c.status = 'active' DESC, live_seed_seconds DESC, p.id DESC
    LIMIT 500");
while ($participantResult && ($row = mysql_fetch_assoc($participantResult))) {
    $participants[] = $row;
}

stdhead('官种保种活动统计');
?>
<style>
  .nova-osa-page{box-sizing:border-box;width:min(1380px,calc(100% - 24px));margin:18px auto 32px;color:#243f43}.nova-osa-page *{box-sizing:border-box}
  .nova-osa-hero{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:20px 24px;border-radius:8px;background:linear-gradient(100deg,#0c2d39,#137f72);color:#fff;box-shadow:0 10px 26px rgba(20,65,68,.16)}.nova-osa-hero h1{margin:0!important;color:#fff!important;font-size:23px!important}.nova-osa-hero p{margin:5px 0 0;color:#d8fffa}.nova-osa-rule{padding:8px 11px;border:1px solid rgba(255,255,255,.22);border-radius:6px;background:rgba(255,255,255,.09);white-space:nowrap}
  .nova-osa-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin:12px 0}.nova-osa-card{padding:14px;border:1px solid #d7e8e5;border-radius:7px;background:#fff;text-align:center}.nova-osa-card b{display:block;color:#0f766e;font-size:21px}.nova-osa-card span{color:#6b7e80;font-size:12px}
  .nova-osa-panel{margin-top:12px;border:1px solid #d3e6e3;border-radius:8px;background:#fff;overflow:hidden}.nova-osa-panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;background:#ecf7f5}.nova-osa-panel-head h2{margin:0!important;color:#174e51!important;font-size:16px!important}.nova-osa-filter{display:flex;align-items:center;gap:7px}.nova-osa-filter input{width:110px;height:34px;padding:0 9px;border:1px solid #bdd9d5;border-radius:5px}.nova-osa-filter button{height:34px;padding:0 14px;border:0;border-radius:5px;background:#0f766e;color:#fff;cursor:pointer}
  .nova-osa-config{display:grid;grid-template-columns:minmax(0,1fr) auto auto auto;align-items:end;gap:12px;padding:15px 16px}.nova-osa-field label{display:block;margin-bottom:5px;color:#567174;font-size:12px}.nova-osa-field input{width:100%;min-width:230px;height:38px;padding:0 10px;border:1px solid #bad8d3;border-radius:5px;background:#fff}.nova-osa-config button{height:38px;padding:0 18px;border:0;border-radius:5px;background:#0f766e;color:#fff;font-weight:700;cursor:pointer}.nova-osa-config-note{align-self:center;color:#667b7e;font-size:12px}.nova-osa-success{margin:12px 0;padding:10px 14px;border:1px solid #a7e3d5;border-radius:6px;background:#ecfdf7;color:#08745f;text-align:center}
  .nova-osa-scroll{overflow:auto}.nova-osa-table{width:100%;border-collapse:collapse}.nova-osa-table th,.nova-osa-table td{padding:10px 9px;border-bottom:1px solid #e4eeec;text-align:center;white-space:nowrap}.nova-osa-table th{background:#f7fbfa;color:#426267}.nova-osa-table td.name{min-width:300px;max-width:540px;text-align:left;white-space:normal}.nova-osa-table td.name a{color:#dc263d!important;font-weight:750}.nova-osa-pill{display:inline-flex;padding:3px 7px;border-radius:4px;background:#e8f8f2;color:#078066;font-size:12px}.nova-osa-pill.off{background:#f1f3f3;color:#788789}.nova-osa-reward{color:#d97706;font-weight:700}
  @media(max-width:900px){.nova-osa-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.nova-osa-hero,.nova-osa-panel-head{align-items:flex-start;flex-direction:column}.nova-osa-rule{white-space:normal}.nova-osa-filter{flex-wrap:wrap}.nova-osa-config{grid-template-columns:1fr}.nova-osa-field input{min-width:0}}
</style>
<main class="nova-osa-page">
  <header class="nova-osa-hero"><div><h1>官种保种活动统计</h1><p>首次入选：官方标签、发布满7天、当前保种人数少于3</p></div><div class="nova-osa-rule">15天奖励20% · 30天累计奖励50%</div></header>
  <?php if (!empty($_GET['saved'])): ?><div class="nova-osa-success">活动时间已保存，计划任务将在10分钟内同步轮次状态。</div><?php endif; ?>
  <section class="nova-osa-panel"><div class="nova-osa-panel-head"><h2><?= $editableRound ? '配置当前活动轮次 #' . (int)$editableRound['id'] : '创建新活动轮次' ?></h2><span>只有主管和管理员可以修改</span></div>
    <form class="nova-osa-config" method="post" action="official-seed-activity-action.php">
      <input type="hidden" name="token" value="<?= htmlspecialchars($roundToken) ?>"><input type="hidden" name="round_id" value="<?= $editableRound ? (int)$editableRound['id'] : 0 ?>">
      <div class="nova-osa-field"><label>活动开始时间</label><input type="datetime-local" name="starts_at" required value="<?= $editableRound ? htmlspecialchars(date('Y-m-d\\TH:i', strtotime($editableRound['starts_at']))) : htmlspecialchars(date('Y-m-d\\TH:i')) ?>"></div>
      <div class="nova-osa-field"><label>活动结束时间</label><input type="datetime-local" name="ends_at" required value="<?= $editableRound ? htmlspecialchars(date('Y-m-d\\TH:i', strtotime($editableRound['ends_at']))) : htmlspecialchars(date('Y-m-d\\TH:i', strtotime('+30 days'))) ?>"></div>
      <button type="submit"><?= $editableRound ? '保存活动时间' : '创建活动轮次' ?></button><div class="nova-osa-config-note">断开超过48小时重新计时；轮次结束后自动衔接下一轮。</div>
    </form>
  </section>
  <section class="nova-osa-summary">
    <div class="nova-osa-card"><b><?= (int)$summary['active_campaigns'] ?></b><span>活动种子</span></div><div class="nova-osa-card"><b><?= (int)$summary['participants'] ?></b><span>参与记录</span></div><div class="nova-osa-card"><b><?= (int)$summary['tier15'] ?></b><span>已达15天</span></div><div class="nova-osa-card"><b><?= (int)$summary['tier30'] ?></b><span>已达30天</span></div><div class="nova-osa-card"><b><?= htmlspecialchars(mksize((int)$summary['reward_bytes'])) ?></b><span>累计发放上传量</span></div>
  </section>
  <section class="nova-osa-panel"><div class="nova-osa-panel-head"><h2>活动种子统计</h2><span>最多显示最近300条</span></div><div class="nova-osa-scroll"><table class="nova-osa-table"><thead><tr><th>ID</th><th>种子名称</th><th>活动状态</th><th>当前保种</th><th>参与人数</th><th>15天人数</th><th>30天人数</th><th>累计奖励</th><th>入选时间</th></tr></thead><tbody>
  <?php foreach ($campaigns as $row): ?><tr><td><?= (int)$row['torrent_id'] ?></td><td class="name"><a href="details.php?id=<?= (int)$row['torrent_id'] ?>"><?= htmlspecialchars($row['name'] ?: '种子已删除') ?></a></td><td><span class="nova-osa-pill<?= $row['status'] === 'active' ? '' : ' off' ?>"><?= $row['status'] === 'active' ? '活动中' : '已关闭' ?></span></td><td><?= (int)$row['seeders'] ?></td><td><?= (int)$row['participant_count'] ?></td><td><?= (int)$row['tier15_count'] ?></td><td><?= (int)$row['tier30_count'] ?></td><td class="nova-osa-reward"><?= htmlspecialchars(mksize((int)$row['reward_bytes'])) ?></td><td><?= htmlspecialchars($row['qualified_at']) ?></td></tr><?php endforeach; ?>
  <?php if (!$campaigns): ?><tr><td colspan="9">当前没有符合条件的官种。</td></tr><?php endif; ?>
  </tbody></table></div></section>
  <section class="nova-osa-panel"><div class="nova-osa-panel-head"><h2>用户保种明细</h2><form class="nova-osa-filter" method="get"><input type="number" min="1" name="torrent_id" value="<?= $torrentId ?: '' ?>" placeholder="种子ID"><input type="number" min="1" name="user_id" value="<?= $userId ?: '' ?>" placeholder="用户ID"><button type="submit">筛选</button></form></div><div class="nova-osa-scroll"><table class="nova-osa-table"><thead><tr><th>用户</th><th>种子</th><th>连接状态</th><th>实际保种</th><th>15天奖励</th><th>30天补发</th><th>累计奖励</th><th>加入时间</th></tr></thead><tbody>
  <?php foreach ($participants as $row): ?><tr><td><a href="userdetails.php?id=<?= (int)$row['user_id'] ?>"><?= htmlspecialchars($row['username']) ?></a> (#<?= (int)$row['user_id'] ?>)</td><td class="name"><a href="details.php?id=<?= (int)$row['torrent_id'] ?>">#<?= (int)$row['torrent_id'] ?> <?= htmlspecialchars($row['name']) ?></a></td><td><span class="nova-osa-pill<?= (int)$row['is_seeding'] ? '' : ' off' ?>"><?= (int)$row['is_seeding'] ? '正在保种' : '未连接' ?></span></td><td><?= htmlspecialchars(nova_official_seed_duration((int)$row['live_seed_seconds'])) ?></td><td><?= $row['reward_15_at'] ? htmlspecialchars(mksize((int)$row['reward_15_bytes'])) : '未发放' ?></td><td><?= $row['reward_30_at'] ? htmlspecialchars(mksize((int)$row['reward_30_bytes'])) : '未发放' ?></td><td class="nova-osa-reward"><?= htmlspecialchars(mksize((int)$row['reward_15_bytes'] + (int)$row['reward_30_bytes'])) ?></td><td><?= htmlspecialchars($row['enrolled_at']) ?></td></tr><?php endforeach; ?>
  <?php if (!$participants): ?><tr><td colspan="8">没有符合筛选条件的用户记录。</td></tr><?php endif; ?>
  </tbody></table></div></section>
</main>
<?php
stdfoot();
