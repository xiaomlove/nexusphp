<?php

require_once __DIR__ . '/../include/bittorrent.php';
dbconn();
loggedinorreturn();

$isManager = nova_official_seed_is_manager();
$viewerId = (int)$CURUSER['id'];
$requestedUserId = (int)($_GET['user_id'] ?? 0);
$scopeUserId = $isManager && $requestedUserId > 0 ? $requestedUserId : ($isManager ? 0 : $viewerId);
$magicFilter = $scopeUserId > 0 ? ' WHERE e.user_id = ' . $scopeUserId : '';
$uploadFilter = $scopeUserId > 0 ? ' WHERE p.user_id = ' . $scopeUserId : '';
$activityCacheFile=sys_get_temp_dir()."/nexus_activity_".(int)$scopeUserId."_".(int)$isManager.".html";
if(is_file($activityCacheFile) && time()-filemtime($activityCacheFile)<120){readfile($activityCacheFile);exit;}
ob_start();
register_shutdown_function(function() use($activityCacheFile){$out=ob_get_contents();if($out!==false)@file_put_contents($activityCacheFile,$out,LOCK_EX);});

function activity_rows(string $sql): array
{
    $result = sql_query($sql);
    $rows = [];
    while ($result && ($row = mysql_fetch_assoc($result))) {
        $rows[] = $row;
    }
    return $rows;
}

$magicWhere = $scopeUserId > 0 ? ' WHERE user_id = ' . $scopeUserId : '';
$uploadWhere = $scopeUserId > 0 ? ' WHERE user_id = ' . $scopeUserId : '';
$boostWhere = $scopeUserId > 0 ? ' AND p.user_id = ' . $scopeUserId : '';
$magicSummary = mysql_fetch_assoc(sql_query('SELECT COALESCE(SUM(reward_amount),0) AS total, COUNT(*) AS records FROM nova_official_seed_magic_reward_events' . $magicWhere));
$uploadSummary = mysql_fetch_assoc(sql_query('SELECT COALESCE(SUM(reward_bytes),0) AS total, COUNT(*) AS records FROM nova_official_seed_reward_events' . $uploadWhere));
$boostSummary = mysql_fetch_assoc(sql_query("SELECT COALESCE(SUM(p.reward),0) AS total, COALESCE(SUM(p.reward > 0),0) AS records
    FROM nova_sticky_promotion_participants p
    JOIN nova_sticky_promotions c ON c.id=p.campaign_id
    WHERE c.sponsor_id=0{$boostWhere}"));

$magicRows = activity_rows('SELECT e.*,u.username FROM nova_official_seed_magic_reward_events e JOIN users u ON u.id=e.user_id' . $magicFilter . ' ORDER BY e.id DESC LIMIT 240');
$uploadRows = activity_rows('SELECT p.user_id,u.username,COUNT(DISTINCT p.torrent_id) AS torrent_count,SUM(p.reward_15_at IS NOT NULL) AS reward_15_count,SUM(p.reward_30_at IS NOT NULL) AS reward_30_count FROM nova_official_seed_participants p JOIN users u ON u.id=p.user_id' . $uploadFilter . ' GROUP BY p.user_id,u.username ORDER BY torrent_count DESC,p.user_id DESC LIMIT 500');
$boostRows = activity_rows("SELECT p.user_id,u.username,
        COUNT(DISTINCT c.torrent_id) AS torrent_count,
        SUM(IF(p.final_uploaded>=p.baseline_uploaded,p.final_uploaded-p.baseline_uploaded,0)) AS uploaded_delta,
        SUM(p.reward) AS reward,MAX(c.settled_at) AS settled_at
    FROM nova_sticky_promotion_participants p
    JOIN nova_sticky_promotions c ON c.id=p.campaign_id
    JOIN users u ON u.id=p.user_id
    WHERE c.sponsor_id=0 AND c.status='settled' AND p.reward>0{$boostWhere}
    GROUP BY p.user_id,u.username
    ORDER BY settled_at DESC,reward DESC LIMIT 240");
$users = $isManager ? activity_rows("SELECT u.id,u.username,
        COALESCE(m.total,0) magic_total,COALESCE(m.records,0) magic_records,
        COALESCE(v.total,0) upload_total,COALESCE(v.records,0) upload_records,
        COALESCE(s.total,0) boost_total,COALESCE(s.records,0) boost_records,
        COALESCE(m.total,0)+COALESCE(s.total,0) combined_magic,
        COALESCE(m.records,0)+COALESCE(v.records,0)+COALESCE(s.records,0) total_records
    FROM users u
    JOIN (
        SELECT user_id FROM nova_official_seed_magic_reward_events
        UNION SELECT user_id FROM nova_official_seed_reward_events
        UNION SELECT p.user_id FROM nova_sticky_promotion_participants p
            JOIN nova_sticky_promotions c ON c.id=p.campaign_id
            WHERE c.sponsor_id=0 AND c.status='settled' AND p.reward>0
    ) x ON x.user_id=u.id
    LEFT JOIN (
        SELECT user_id,SUM(reward_amount) total,COUNT(*) records
        FROM nova_official_seed_magic_reward_events GROUP BY user_id
    ) m ON m.user_id=u.id
    LEFT JOIN (
        SELECT user_id,SUM(reward_bytes) total,COUNT(*) records
        FROM nova_official_seed_reward_events GROUP BY user_id
    ) v ON v.user_id=u.id
    LEFT JOIN (
        SELECT p.user_id,SUM(p.reward) total,COUNT(*) records
        FROM nova_sticky_promotion_participants p
        JOIN nova_sticky_promotions c ON c.id=p.campaign_id
        WHERE c.sponsor_id=0 AND c.status='settled' AND p.reward>0
        GROUP BY p.user_id
    ) s ON s.user_id=u.id" . ($scopeUserId > 0 ? ' WHERE u.id=' . $scopeUserId : '') . ' ORDER BY combined_magic DESC,upload_total DESC,total_records DESC LIMIT 500') : [];

stdhead('活动奖励');
?>
<style>
.activity-rewards{width:min(1320px,calc(100% - 24px));margin:18px auto 32px;color:#25484b}.activity-rewards *{box-sizing:border-box}.ar-hero{padding:22px 26px;border-radius:8px;background:linear-gradient(105deg,#0b4350,#087a70);color:#fff}.ar-hero h1{margin:0!important;color:#fff!important;font-size:23px!important}.ar-hero p{margin:7px 0 0;color:#d8fbf7}.ar-formula{margin-top:12px;border:1px solid #bfe0db;border-radius:8px;background:#fff;overflow:hidden}.ar-formula h2{margin:0!important;padding:13px 18px;background:#e9f8f5;color:#0f766e!important;font-size:17px!important;text-align:center}.ar-formula-body{display:grid;grid-template-columns:minmax(360px,.9fr) minmax(0,1.1fr);gap:22px;padding:18px 22px}.ar-equations{display:grid;align-content:center;gap:9px;padding:14px 18px;border:1px solid #d9ece9;border-radius:7px;background:#f8fcfb;color:#173f43;font:17px/1.6 Georgia,"Times New Roman",serif}.ar-equations strong{color:#087a70;font-size:19px}.ar-equations small{font:12px/1.6 Arial,"Microsoft YaHei",sans-serif;color:#668184}.ar-vars{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px 18px;margin:0;padding:0;list-style:none;color:#536f73;font-size:12px;line-height:1.65}.ar-vars b{color:#174e52}.ar-formula-note{grid-column:1/-1;margin:0;padding:10px 13px;border-radius:6px;background:#edf8f6;color:#41686c;font-size:12px}.ar-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:12px}.ar-rules{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:12px}.ar-rule{border:1px solid #cfe4e1;border-radius:8px;background:#fff;overflow:hidden}.ar-rule h2{margin:0!important;padding:12px 16px;font-size:16px!important;text-align:center}.ar-rule.magic h2{background:#e9f8f5;color:#0f766e!important}.ar-rule.upload h2{background:#fff5e7;color:#ba6a12!important}.ar-rule.boost h2{background:#edf3ff;color:#4263a8!important}.ar-rule-formula{margin:14px 16px 0;padding:12px 14px;border:1px solid #f0d7b5;border-radius:7px;background:#fffaf2;color:#754510;text-align:center}.ar-rule-formula strong{display:block;color:#ba6a12;font:16px/1.9 Georgia,"Times New Roman",serif}.ar-rule-formula span{display:block;margin-top:5px;color:#7f674d;font-size:11px;line-height:1.6}.ar-rule ul{min-height:178px;margin:0;padding:14px 20px 14px 34px;color:#536f73;font-size:12px;line-height:1.75}.ar-rule li+li{margin-top:5px}.ar-card,.ar-panel{border:1px solid #cfe4e1;border-radius:8px;background:#fff;overflow:hidden}.ar-card h2,.ar-panel h2{margin:0!important;padding:12px 16px;font-size:16px!important;text-align:center}.ar-card.magic h2{background:#e9f8f5;color:#0f766e!important}.ar-card.upload h2{background:#fff5e7;color:#ba6a12!important}.ar-card.boost h2{background:#edf3ff;color:#4263a8!important}.ar-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;background:#dcecea}.ar-metric{padding:16px;background:#fff;text-align:center}.ar-metric b{display:block;color:#0f766e;font-size:22px}.ar-upload{color:#ba6a12!important}.ar-boost{color:#4263a8!important}.ar-metric span{color:#6e8588;font-size:12px}.ar-panel{margin-top:12px}.ar-panel h2{background:#eef9f7;color:#154c50!important}.ar-detail-grid .ar-panel:nth-child(3) h2{background:#edf3ff;color:#4263a8!important}.ar-note{padding:10px 16px;border-bottom:1px solid #dcebea;background:#fbfefd;color:#617c7f;font-size:12px}.ar-scroll{overflow:auto}.ar-table{width:100%;border-collapse:collapse}.ar-table th,.ar-table td{padding:10px 9px;border-bottom:1px solid #e4efed;text-align:center;white-space:nowrap}.ar-table th{background:#f8fcfb;color:#45656a}.ar-table a{color:#0f766e!important;font-weight:700}.ar-count-link{display:inline-flex;min-width:34px;justify-content:center;color:#0f766e!important;font-weight:800;text-decoration:underline;cursor:pointer}.ar-filter{float:right;margin:-42px 14px 0}.ar-filter input{width:100px;height:31px;border:1px solid #bcdad6;border-radius:5px;padding:0 8px}.ar-filter button{height:31px;border:0;border-radius:5px;background:#0f766e;color:#fff;padding:0 12px}.ar-detail-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.ar-modal{position:fixed;inset:0;z-index:9999;display:none;background:rgba(17,52,58,.45)}.ar-modal.open{display:block}.ar-modal-box{position:fixed;width:min(760px,calc(100vw - 24px));max-height:82vh;border-radius:10px;background:#fff;box-shadow:0 20px 60px rgba(9,54,60,.25);overflow:hidden}.ar-modal-head{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;background:#eef9f7;color:#154c50;font-weight:800}.ar-modal-close{border:0;background:transparent;color:#547477;font-size:22px;cursor:pointer}.ar-modal-body{max-height:calc(82vh - 58px);overflow:auto;padding:12px 18px}.ar-seed-list{margin:0;padding:0;list-style:none}.ar-seed-list li{padding:10px 4px;border-bottom:1px solid #e4efed}.ar-seed-list a{color:#0f766e!important;font-weight:700}.ar-seed-empty{text-align:center;padding:26px;color:#789093}@media(max-width:900px){.ar-formula-body{grid-template-columns:1fr}.ar-vars{grid-template-columns:1fr}.ar-grid,.ar-rules,.ar-detail-grid{grid-template-columns:1fr}.ar-rule ul{min-height:0}.ar-filter{float:none;margin:0 14px 12px}}
</style>
<style>
.ar-formula .ar-equations>div,.ar-formula .ar-equations>div strong{color:#087a70}
.ar-formula.upload{border-color:#f0d7b5}.ar-formula.upload h2{background:#fff5e7;color:#ba6a12!important}.ar-formula.upload .ar-equations{border-color:#f0d7b5;background:#fffaf2;color:#754510}.ar-formula.upload .ar-equations>div,.ar-formula.upload .ar-equations>div strong{color:#ba6a12}.ar-formula.upload .ar-equations small{color:#7f674d}.ar-formula.upload .ar-vars b{color:#a95f10}.ar-formula.upload .ar-formula-note{background:#fff7eb;color:#75552f}
.ar-formula.boost{border-color:#cddaf2}.ar-formula.boost h2{background:#edf3ff;color:#4263a8!important}.ar-formula.boost .ar-equations{border-color:#cddaf2;background:#f7f9ff;color:#354f85}.ar-formula.boost .ar-equations>div,.ar-formula.boost .ar-equations>div strong{color:#4263a8}.ar-formula.boost .ar-equations small{color:#647498}.ar-formula.boost .ar-vars b{color:#36578f}.ar-formula.boost .ar-formula-note{background:#f1f5ff;color:#4d628d}
</style>
<main class="activity-rewards">
  <header class="ar-hero"><h1>活动奖励</h1><p>活动魔力值、上传量和系统助推奖励分别独立结算，并与站点正常魔力获取规则分开记录。</p></header>
  <section class="ar-formula" aria-label="官种活动独立魔力值计算公式">
    <h2>官种活动独立魔力值计算公式</h2>
    <div class="ar-formula-body">
      <div class="ar-equations">
        <div>A<sub>e</sub> = &Sigma;<sub>i</sub> (1 - 10<sup>-t<sub>i</sub>/T<sub>0</sub></sup>) &middot; S<sub>i</sub> &middot; (1 + &radic;2 &middot; 10<sup>-(N<sub>i</sub>-1)/(N<sub>0</sub>-1)</sup>)</div>
        <div>B<sub>e</sub> = B<sub>0</sub> &middot; 2/&pi; &middot; arctan(A<sub>e</sub>/L)</div>
        <div>P = &radic;&Sigma;<sub>i</sub> [(1 - 10<sup>-t<sub>i</sub>/T<sub>0</sub></sup>) &middot; (1 + &radic;2 &middot; 10<sup>-(N<sub>i</sub>-1)/(N<sub>0</sub>-1)</sup>) &middot; S<sub>i</sub><sup>3/2</sup>]</div>
        <div><strong>C = B<sub>e</sub> &middot; P/100 &middot; F &middot; 20</strong></div>
        <small>C 为官种活动独立魔力的每小时计算基准，实际发放由活动系统自动完成。</small>
      </div>
      <ul class="ar-vars">
        <li><b>i</b>：当前用户正在保种且符合活动资格的第 i 个官种。</li>
        <li><b>t<sub>i</sub></b>：该官种从发布至当前的存活时间，单位为周。</li>
        <li><b>T<sub>0</sub></b>：时间衰减参数，当前沿用普通魔力设置。</li>
        <li><b>S<sub>i</sub></b>：该官种的文件体积，单位为 GB。</li>
        <li><b>N<sub>i</sub></b>：该官种当前有效做种人数，人数越少，稀缺加成越高。</li>
        <li><b>N<sub>0</sub></b>：做种人数衰减参数，当前沿用普通魔力设置。</li>
        <li><b>B<sub>0</sub>、L</b>：普通魔力基础上限与曲线参数，均沿用站点现有配置。</li>
        <li><b>A<sub>e</sub>、B<sub>e</sub></b>：活动官种贡献总量，以及由普通魔力公式计算出的活动基础值。</li>
        <li><b>P</b>：官种综合加成百分比，由存活时间、稀缺程度和体积共同决定。</li>
        <li><b>F</b>：站点普通魔力全局基础系数，与普通魔力结算保持一致。</li>
        <li><b>C</b>：官种活动独立魔力的每小时计算基准，不包含用户正常做种所得魔力。</li>
      </ul>
      <p class="ar-formula-note"><b>发放方式：</b>只有累计有效做种满 24 小时的活动官种参与计算。系统每小时自动读取用户当时的有效做种状态，计算官种活动独立魔力并直接计入个人魔力余额，同时写入“活动魔力值明细”，无需手动领取。同一用户同一小时只发放一次；普通魔力仍由站点原有魔力系统独立计算和发放，活动系统不会重复发放普通魔力。</p>
    </div>
  </section>
  <section class="ar-formula upload" aria-label="官种活动上传量奖励计算公式">
    <h2>官种活动上传量奖励计算公式</h2>
    <div class="ar-formula-body">
      <div class="ar-equations">
        <div><strong>U(d,S) = 0，d &lt; 15</strong></div>
        <div><strong>U(d,S) = 20% &middot; S，15 &le; d &lt; 30</strong></div>
        <div><strong>U(d,S) = 50% &middot; S，d &ge; 30</strong></div>
        <small>满 15 天首次发放 20% &middot; S；满 30 天补发 30% &middot; S，累计奖励达到 50% &middot; S。</small>
      </div>
      <ul class="ar-vars">
        <li><b>S</b>：当前活动官种的完整资源体积，系统按字节精确计算。</li>
        <li><b>d</b>：用户对该资源的连续有效保种天数。</li>
        <li><b>U(d,S)</b>：截至当前保种档位，用户应获得的累计上传量奖励。</li>
        <li><b>d &lt; 15</b>：尚未达到最低奖励条件，不发放上传量。</li>
        <li><b>15 &le; d &lt; 30</b>：达到第一档，累计奖励为资源体积的 20%。</li>
        <li><b>d &ge; 30</b>：达到第二档，累计奖励为资源体积的 50%。</li>
        <li><b>30 天补发</b>：系统只补发剩余的 30%，不会重复发放15天档的20%。</li>
        <li><b>连续保种</b>：活动期间断开保种超过48小时，连续保种时间重新计算。</li>
      </ul>
      <p class="ar-formula-note"><b>参与及发放方式：</b>发布满15天且当前保种人数低于3人的官种进入上传量奖励活动。用户达到对应连续保种档位后，系统自动将奖励直接增加到个人上传量，写入“上传量奖励明细”并发送站内通知，无需手动领取；同一用户对同一资源的每个档位只发放一次。</p>
    </div>
  </section>
  <section class="ar-formula boost" aria-label="新官种活动助推奖励计算公式">
    <h2>新官种活动助推奖励计算公式</h2>
    <div class="ar-formula-body">
      <div class="ar-equations">
        <div>Q<sub>j</sub> = max(U<sub>j,end</sub> - U<sub>j,start</sub>, 0)</div>
        <div>W = Top 10 {j | Q<sub>j</sub> &gt; 0}</div>
        <div><strong>R<sub>j</sub> = 3000 &middot; Q<sub>j</sub> / &Sigma;<sub>k&isin;W</sub> Q<sub>k</sub>，j &isin; W</strong></div>
        <div>R<sub>j</sub> = 0，j &notin; W</div>
        <small>奖励按上传贡献比例分配，而不是平均分配；贡献越高，在3000魔力奖励池中获得的比例越高。</small>
      </div>
      <ul class="ar-vars">
        <li><b>j</b>：参与当前新官种助推活动的第 j 位用户。</li>
        <li><b>U<sub>j,start</sub></b>：活动开始时记录的该用户上传量基准。</li>
        <li><b>U<sub>j,end</sub></b>：活动结束时该用户在此资源上的累计上传量。</li>
        <li><b>Q<sub>j</sub></b>：该用户在48小时活动期间产生的有效上传贡献。</li>
        <li><b>W</b>：有效上传贡献大于0并排名前10的获奖用户集合。</li>
        <li><b>&Sigma;Q<sub>k</sub></b>：本轮前10名获奖用户的有效上传贡献总和。</li>
        <li><b>R<sub>j</sub></b>：该用户本轮最终获得的助推魔力奖励。</li>
        <li><b>同贡献排序</b>：上传贡献相同时，系统按用户ID由小到大确定顺序。</li>
      </ul>
      <p class="ar-formula-note"><b>参与及发放方式：</b>所有新发布官种由系统自动开启48小时助推活动，奖励池为3000魔力。活动结束后，系统按有效上传贡献完成排名和比例分配，奖励直接计入获奖用户的个人魔力余额，同时写入“活动助推奖励明细”并发送站内通知，无需手动领取。若有效参与者不足10人，则只在实际符合条件的用户之间按比例分配。</p>
    </div>
  </section>
  <section class="ar-rules" aria-label="活动规则">
    <article class="ar-rule magic">
      <h2>活动魔力值奖励规则</h2>
      <ul>
        <li>保种区内全部官种均参与计算，包括同时参加上传量奖励活动的官种。</li>
        <li>用户连续做种满 24 小时后，每小时按“普通魔力值公式 × 官种加成”计算原始奖励；所有符合条件的参与者均可获得。</li>
        <li>系统每小时自动结算并直接发放到个人魔力余额，同时生成活动魔力值明细，无需手动领取。</li>
        <li>本活动只发放官种活动独立魔力；普通魔力由站点原有魔力系统自动发放，活动结算不会重复发放普通魔力。</li>
      </ul>
    </article>
    <article class="ar-rule upload">
      <h2>活动上传量奖励规则</h2>
      <ul>
        <li>发布满 15 天且保种人数低于 3 人的官种，进入上传量奖励活动。</li>
        <li>连续保种不足 15 天不发放奖励；满 15 天奖励资源体积的 20%。</li>
        <li>连续保种满 30 天累计奖励资源体积的 50%；15 天以上但不足 30 天按 20% 结算。</li>
        <li>活动期间断开保种超过 48 小时，连续保种时间将重新计算。</li>
      </ul>
    </article>
    <article class="ar-rule boost">
      <h2>活动助推奖励规则</h2>
      <ul>
        <li>所有新发布的官种由系统自动加入助推活动。</li>
        <li>每轮助推活动固定持续 48 小时，系统奖励池为 3000 魔力。</li>
        <li>活动结束后，按照活动期间的有效上传贡献进行排名和奖励分配。</li>
        <li>每轮最多奖励上传贡献排名前 10 名用户，奖励由系统统一发放。</li>
      </ul>
    </article>
  </section>
  <section class="ar-grid">
    <article class="ar-card magic"><h2>活动魔力值奖励</h2><div class="ar-metrics"><div class="ar-metric"><b><?= number_format((float)$magicSummary['total'], 2) ?></b><span>活动魔力值</span></div><div class="ar-metric"><b><?= (int)$magicSummary['records'] ?></b><span>结算次数</span></div></div></article>
    <article class="ar-card upload"><h2>活动上传量奖励</h2><div class="ar-metrics"><div class="ar-metric"><b class="ar-upload"><?= htmlspecialchars(mksize((int)$uploadSummary['total'])) ?></b><span>已获上传量</span></div><div class="ar-metric"><b class="ar-upload"><?= (int)$uploadSummary['records'] ?></b><span>发放次数</span></div></div></article>
    <article class="ar-card boost"><h2>活动助推奖励</h2><div class="ar-metrics"><div class="ar-metric"><b class="ar-boost"><?= number_format((float)$boostSummary['total'], 2) ?></b><span>已获助推魔力</span></div><div class="ar-metric"><b class="ar-boost"><?= (int)$boostSummary['records'] ?></b><span>获奖次数</span></div></div></article>
  </section>
  <section class="ar-grid ar-detail-grid">
    <section class="ar-panel"><h2>活动魔力值明细</h2><div class="ar-scroll"><table class="ar-table"><thead><tr><th>用户</th><th>奖励魔力</th><th>官种数量</th><th>官种加成</th><th>结算时间</th></tr></thead><tbody><?php foreach ($magicRows as $row): ?><tr><td><a href="userdetails.php?id=<?= (int)$row['user_id'] ?>"><?= htmlspecialchars($row['username']) ?></a></td><td><?= number_format((float)$row['reward_amount'], 5) ?></td><td><a class="ar-count-link ar-seed-trigger" href="#" data-user-id="<?= (int)$row['user_id'] ?>" data-source="magic"><?= (int)$row['torrent_count'] ?></a></td><td><?= number_format((float)$row['official_percent'], 2) ?>%</td><td><?= htmlspecialchars($row['created_at']) ?></td></tr><?php endforeach; ?><?php if (!$magicRows): ?><tr><td colspan="5">暂无活动魔力值记录</td></tr><?php endif; ?></tbody></table></div></section>
    <section class="ar-panel"><h2>上传量奖励明细</h2><div class="ar-scroll"><table class="ar-table"><thead><tr><th>用户</th><th>参与资源数</th><th>15 天结算</th><th>30 天结算</th></tr></thead><tbody><?php foreach ($uploadRows as $row): ?><tr><td><a href="userdetails.php?id=<?= (int)$row['user_id'] ?>"><?= htmlspecialchars($row['username']) ?></a></td><td><a class="ar-count-link ar-seed-trigger" href="#" data-user-id="<?= (int)$row['user_id'] ?>" data-source="upload"><?= (int)$row['torrent_count'] ?></a></td><td class="ar-upload"><?= (int)$row['reward_15_count'] ?></td><td class="ar-upload"><?= (int)$row['reward_30_count'] ?></td></tr><?php endforeach; ?><?php if (!$uploadRows): ?><tr><td colspan="4">暂无上传量奖励记录</td></tr><?php endif; ?></tbody></table></div></section>
    <section class="ar-panel"><h2>活动助推奖励明细</h2><div class="ar-scroll"><table class="ar-table"><thead><tr><th>用户</th><th>官种资源</th><th>上传贡献</th><th>奖励魔力</th><th>结算时间</th></tr></thead><tbody><?php foreach ($boostRows as $row): ?><tr><td><a href="userdetails.php?id=<?= (int)$row['user_id'] ?>"><?= htmlspecialchars($row['username']) ?></a></td><td><a class="ar-count-link ar-seed-trigger" href="#" data-user-id="<?= (int)$row['user_id'] ?>" data-source="boost"><?= (int)$row['torrent_count'] ?></a></td><td><?= htmlspecialchars(mksize((int)$row['uploaded_delta'])) ?></td><td class="ar-boost"><?= number_format((float)$row['reward'], 2) ?></td><td><?= htmlspecialchars($row['settled_at']) ?></td></tr><?php endforeach; ?><?php if (!$boostRows): ?><tr><td colspan="5">暂无活动助推奖励记录</td></tr><?php endif; ?></tbody></table></div></section>
  </section>
  <?php if ($isManager): ?>
  <section class="ar-panel"><h2>全部活动奖励统计表</h2><form class="ar-filter" method="get"><input type="number" min="1" name="user_id" placeholder="用户 ID" value="<?= $scopeUserId ?: '' ?>"><button>筛选</button></form><div class="ar-note">汇总活动魔力值、上传量奖励和助推奖励；活动魔力合计为活动魔力值与助推魔力之和。主管和管理员可查看全部用户，筛选后仅显示指定用户。</div><div class="ar-scroll"><table class="ar-table"><thead><tr><th>用户</th><th>活动魔力值</th><th>魔力结算</th><th>活动上传量</th><th>上传量发放</th><th>助推魔力</th><th>助推获奖</th><th>活动魔力合计</th><th>总记录数</th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td><a href="userdetails.php?id=<?= (int)$user['id'] ?>"><?= htmlspecialchars($user['username']) ?></a></td><td><?= number_format((float)$user['magic_total'], 2) ?></td><td><?= (int)$user['magic_records'] ?></td><td class="ar-upload"><?= htmlspecialchars(mksize((int)$user['upload_total'])) ?></td><td><?= (int)$user['upload_records'] ?></td><td class="ar-boost"><?= number_format((float)$user['boost_total'], 2) ?></td><td><?= (int)$user['boost_records'] ?></td><td><b><?= number_format((float)$user['combined_magic'], 2) ?></b></td><td><?= (int)$user['total_records'] ?></td></tr><?php endforeach; ?><?php if (!$users): ?><tr><td colspan="9">暂无活动奖励记录</td></tr><?php endif; ?></tbody></table></div></section>
  <?php endif; ?>
</main>
<div class="ar-modal" id="ar-seed-modal" aria-hidden="true"><div class="ar-modal-box" role="dialog" aria-modal="true" aria-labelledby="ar-modal-title"><div class="ar-modal-head"><span id="ar-modal-title">正在做种的资源</span><button type="button" class="ar-modal-close" aria-label="关闭">&times;</button></div><div class="ar-modal-body" id="ar-modal-body"><div class="ar-seed-empty">加载中...</div></div></div></div>
<script>
(function () {
  const modal = document.getElementById('ar-seed-modal');
  const box = modal && modal.querySelector('.ar-modal-box');
  const body = document.getElementById('ar-modal-body');
  const title = document.getElementById('ar-modal-title');
  if (!modal) return;
  function closeModal() { modal.classList.remove('open'); modal.setAttribute('aria-hidden', 'true'); }
  function placeModal(x, y) {
    requestAnimationFrame(function () {
      const rect = box.getBoundingClientRect();
      const margin = 12;
      const left = Math.max(margin, Math.min(window.innerWidth - rect.width - margin, x - rect.width / 2));
      const top = Math.max(margin, Math.min(window.innerHeight - rect.height - margin, y - rect.height / 2));
      box.style.left = left + 'px';
      box.style.top = top + 'px';
    });
  }
  document.querySelectorAll('.ar-seed-trigger').forEach(function (trigger) {
    trigger.addEventListener('click', function (event) {
      event.preventDefault();
      const userId = Number(trigger.dataset.userId || 0);
      const source = trigger.dataset.source || '';
      if (!userId || !source) return;
      title.textContent = '正在做种的资源';
      body.innerHTML = '<div class="ar-seed-empty">加载中...</div>';
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
      placeModal(event.clientX || window.innerWidth / 2, event.clientY || window.innerHeight / 2);
      fetch('activity-reward-seeding.php?user_id=' + encodeURIComponent(userId) + '&source=' + encodeURIComponent(source), { credentials: 'same-origin' })
        .then(function (response) { return response.text(); })
        .then(function (html) { body.innerHTML = html; placeModal(event.clientX || window.innerWidth / 2, event.clientY || window.innerHeight / 2); })
        .catch(function () { body.innerHTML = '<div class="ar-seed-empty">资源列表加载失败，请稍后重试。</div>'; });
    });
  });
  modal.querySelector('.ar-modal-close').addEventListener('click', closeModal);
  modal.addEventListener('click', function (event) { if (event.target === modal) closeModal(); });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeModal(); });
})();
</script>
<?php stdfoot();
