<?php

/**
 * NovaHD official torrent seeding activity UI integration.
 */

function nova_official_seed_is_manager(): bool
{
    return get_user_class() >= UC_STAFFLEADER;
}

function nova_official_seed_round_token(int $userId): string
{
    return hash_hmac('sha256', $userId . '|official-seed-round|' . date('Y-m-d'), (string)config('app.key'));
}

function nova_official_seed_verify_round_token(string $token, int $userId): bool
{
    return $token !== '' && hash_equals(nova_official_seed_round_token($userId), $token);
}

function nova_official_seed_reward_condition(string $type, string $torrentAlias = 'torrents'): string
{
    if (!in_array($type, ['all', 'magic', 'upload'], true)) {
        throw new InvalidArgumentException('Unsupported official seed reward type.');
    }
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $torrentAlias)) {
        throw new InvalidArgumentException('Invalid torrent table alias.');
    }

    $required = "EXISTS (
        SELECT 1 FROM require_seed_torrents rs
        WHERE rs.torrent_id = {$torrentAlias}.id
    )";
    $upload = "EXISTS (
        SELECT 1
        FROM nova_official_seed_campaigns c
        JOIN nova_official_seed_rounds r ON r.id = c.round_id
        WHERE c.torrent_id = {$torrentAlias}.id
          AND c.status = 'active'
          AND r.status = 'active'
          AND r.starts_at <= NOW()
          AND r.ends_at > NOW()
    )";

    return match ($type) {
        'all' => "({$required})",
        'magic' => "({$required}) AND NOT ({$upload})",
        'upload' => "({$required}) AND ({$upload})",
    };
}

function nova_official_seed_reward_label(string $type): string
{
    return $type === 'upload' ? '官种上传量奖励' : '官种魔力值奖励';
}

function nova_official_seed_render_download_tools(string $type, array $user): string
{
    if (!in_array($type, ['magic', 'upload'], true) || empty($user['passkey'])) {
        return '';
    }
    $label = nova_official_seed_reward_label($type);
    $rss = 'torrentrss.php?' . http_build_query([
        'passkey' => $user['passkey'],
        'activity_reward' => $type,
        'rows' => 'all',
        'linktype' => 'dl',
    ], '', '&', PHP_QUERY_RFC3986);
    $download = 'activity-reward-download.php?' . http_build_query(['type' => $type]);

    return '<div class="nova-reward-tools">'
        . '<span class="nova-reward-tools-title">' . htmlspecialchars($label) . '</span>'
        . '<a class="nova-reward-tool nova-reward-tool-download" href="' . htmlspecialchars($download) . '">一键下载</a>'
        . '<a class="nova-reward-tool nova-reward-tool-rss" href="' . htmlspecialchars($rss) . '" target="_blank" rel="noopener">RSS 订阅</a>'
        . '</div>';
}

function nova_official_seed_render_download_tools_style(): string
{
    static $rendered = false;
    if ($rendered) {
        return '';
    }
    $rendered = true;
    return '<style>
      .nova-reward-toolbox{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px;margin:0 0 10px}
      .nova-reward-tools{display:flex;align-items:center;gap:6px;padding:5px 6px 5px 12px;border:1px solid #c9e0e8;border-radius:6px;background:#f5fbfd;box-shadow:0 3px 10px rgba(26,83,104,.07)}
      .nova-reward-tools-title{margin-right:3px;color:#315b6a;font-weight:700;white-space:nowrap}
      .nova-reward-tool{display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;min-height:30px;padding:0 11px;border:1px solid;border-radius:5px;font-weight:700;text-decoration:none!important;white-space:nowrap}
      .nova-reward-tool-download{border-color:#df9c35;background:#fff8e9;color:#a65b00!important}
      .nova-reward-tool-rss{border-color:#62aebe;background:#eaf8fb;color:#08758b!important}
      .nova-reward-tool:hover{filter:brightness(.97);box-shadow:0 2px 7px rgba(25,74,76,.12)}
      @media(max-width:720px){.nova-reward-toolbox{justify-content:stretch}.nova-reward-tools{width:100%;flex-wrap:wrap}.nova-reward-tools-title{flex:1}.nova-reward-tool{min-width:82px}}
    </style>';
}

function nova_official_seed_is_active(int $torrentId): bool
{
    static $cache = [];
    if (array_key_exists($torrentId, $cache)) {
        return $cache[$torrentId];
    }
    $result = sql_query("SELECT c.id FROM nova_official_seed_campaigns c
        JOIN nova_official_seed_rounds r ON r.id = c.round_id
        WHERE c.torrent_id = {$torrentId} AND c.status = 'active' AND r.status = 'active'
          AND r.starts_at <= NOW() AND r.ends_at > NOW() LIMIT 1");
    return $cache[$torrentId] = (bool)($result && mysql_num_rows($result));
}

function nova_official_seed_is_magic_reward(int $torrentId): bool
{
    static $cache = [];
    if (array_key_exists($torrentId, $cache)) {
        return $cache[$torrentId];
    }
    $result = sql_query("SELECT torrent_id FROM require_seed_torrents WHERE torrent_id = {$torrentId} LIMIT 1");
    return $cache[$torrentId] = (bool)($result && mysql_num_rows($result));
}

function nova_official_seed_sticky_marker(string $stickyIcon, array $torrent): string
{
    $torrentId = (int)($torrent['id'] ?? 0);
    if ($torrentId <= 0) {
        return $stickyIcon;
    }
    $isUploadReward = nova_official_seed_is_active($torrentId);
    $isMagicReward = !$isUploadReward && nova_official_seed_is_magic_reward($torrentId);
    if (!$isUploadReward && !$isMagicReward) {
        return $stickyIcon;
    }
    static $styleRendered = false;
    $style = '';
    if (!$styleRendered) {
        $styleRendered = true;
        $style = '<style>
          .nova-official-seed-marker{display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;min-width:112px;height:19px;margin-right:6px;padding:0 6px;border:1px solid;border-radius:4px;font-size:11px;font-weight:750;line-height:1;vertical-align:middle;letter-spacing:0}
          .nova-official-seed-upload{border-color:rgba(239,68,68,.28);background:#fff1f2;color:#dc2626!important}
          .nova-official-seed-magic{border-color:rgba(16,185,129,.30);background:#ecfdf5;color:#07805f!important}
          .nova-official-seed-upload+a,.torrentname td.embedded:has(.nova-official-seed-upload)>a{color:#df2638!important;-webkit-text-fill-color:#df2638!important;text-decoration-color:rgba(223,38,56,.35)!important}
          .nova-official-seed-upload+a:hover,.torrentname td.embedded:has(.nova-official-seed-upload)>a:hover{color:#b9142a!important;-webkit-text-fill-color:#b9142a!important}
          .nova-official-seed-magic+a,.torrentname td.embedded:has(.nova-official-seed-magic)>a{color:#2563eb!important;-webkit-text-fill-color:#2563eb!important;text-decoration-color:rgba(37,99,235,.35)!important}
          .nova-official-seed-magic+a:hover,.torrentname td.embedded:has(.nova-official-seed-magic)>a:hover{color:#1d4ed8!important;-webkit-text-fill-color:#1d4ed8!important}
        </style>';
    }
    if ($isUploadReward) {
        return $style . $stickyIcon . '<span class="nova-official-seed-marker nova-official-seed-upload" title="官种上传量活动：实际做种满15天奖励20%，满30天累计奖励50%">官种上传量奖励</span>';
    }
    return $style . $stickyIcon . '<span class="nova-official-seed-marker nova-official-seed-magic" title="官种魔力活动">官种魔力值奖励</span>';
}

function nova_official_seed_duration(int $seconds): string
{
    $seconds = max(0, $seconds);
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    return $days . '天' . $hours . '小时';
}

function nova_official_seed_personal_rows(int $userId): array
{
    $sql = "SELECT p.*, c.status AS campaign_status, c.size_bytes, t.name, t.seeders,
                GREATEST(COALESCE(s.seedtime, p.baseline_seedtime) - p.baseline_seedtime, p.seed_seconds, 0) AS live_seed_seconds,
                EXISTS(SELECT 1 FROM peers px WHERE px.torrent = p.torrent_id AND px.userid = p.user_id AND px.seeder = 'yes') AS is_seeding
            , r.id AS round_id, r.starts_at AS round_starts_at, r.ends_at AS round_ends_at, r.status AS round_status
            FROM nova_official_seed_participants p
            JOIN nova_official_seed_campaigns c ON c.id = p.campaign_id
            JOIN nova_official_seed_rounds r ON r.id = c.round_id
            JOIN torrents t ON t.id = p.torrent_id
            LEFT JOIN snatched s ON s.torrentid = p.torrent_id AND s.userid = p.user_id
            WHERE p.user_id = {$userId}
            ORDER BY c.status = 'active' DESC, p.updated_at DESC, p.id DESC
            LIMIT 200";
    $result = sql_query($sql);
    $rows = [];
    while ($result && ($row = mysql_fetch_assoc($result))) {
        $rows[] = $row;
    }
    return $rows;
}

function nova_official_seed_render_personal_panel(int $userId): string
{
    global $CURUSER;
    if (!$CURUSER || (int)$CURUSER['id'] !== $userId) {
        return '';
    }
    $magic = mysql_fetch_assoc(sql_query("SELECT COALESCE(SUM(reward_amount),0) AS total,COUNT(*) AS records FROM nova_official_seed_magic_reward_events WHERE user_id={$userId}"));
    $upload = mysql_fetch_assoc(sql_query("SELECT COALESCE(SUM(reward_bytes),0) AS total,COUNT(*) AS records FROM nova_official_seed_reward_events WHERE user_id={$userId}"));
    $boost = mysql_fetch_assoc(sql_query("SELECT COALESCE(SUM(p.reward),0) AS total,COUNT(*) AS records
        FROM nova_sticky_promotion_participants p
        JOIN nova_sticky_promotions c ON c.id=p.campaign_id
        WHERE p.user_id={$userId} AND c.sponsor_id=0 AND c.status='settled' AND p.reward>0"));
    ob_start();
    ?>
    <style>
      .nova-official-personal{width:min(1455px,calc(100% - 20px));margin:18px auto 8px;padding:0;border:1px solid #cce4e1;border-radius:8px;background:#fff;box-shadow:0 7px 20px rgba(25,74,76,.09);overflow:hidden}
      .nova-official-personal *{box-sizing:border-box}
      .nova-official-personal-head{padding:14px 18px;background:linear-gradient(100deg,#0c2d39,#137f72);color:#fff;text-align:center}
      .nova-official-personal-head strong{font-size:17px}
      .nova-official-reward-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));width:100%;background:#fff}
      .nova-official-reward-item{display:flex;min-width:0;min-height:132px;padding:22px 18px;align-items:center;justify-content:center;flex-direction:column;text-align:center}
      .nova-official-reward-item+.nova-official-reward-item{border-left:1px solid #dceae8}
      .nova-official-reward-name{color:#31575d;font-size:14px;font-weight:750;line-height:1.35}
      .nova-official-reward-value{margin:13px 0 10px;font-size:20px;font-weight:850;line-height:1.2}
      .nova-official-reward-value.magic{color:#0f766e}
      .nova-official-reward-value.upload{color:#ba6a12}
      .nova-official-reward-value.boost{color:#4263a8}
      .nova-official-reward-count{color:#71888b;font-size:12px;line-height:1.3}
      .nova-official-reward-count strong{margin-left:4px;color:#365c63;font-size:13px}
      @media(max-width:620px){
        .nova-official-reward-item{min-height:116px;padding:16px 7px}
        .nova-official-reward-name{font-size:12px}
        .nova-official-reward-value{margin:10px 0 8px;font-size:16px}
        .nova-official-reward-count{font-size:11px}
      }
    </style>
    <section class="nova-official-personal">
      <div class="nova-official-personal-head"><strong>我的活动奖励</strong></div>
      <div class="nova-official-reward-grid">
        <div class="nova-official-reward-item">
          <span class="nova-official-reward-name">活动魔力值奖励</span>
          <strong class="nova-official-reward-value magic"><?= number_format((float)$magic['total'], 2) ?> 魔力</strong>
          <span class="nova-official-reward-count">发放次数<strong><?= (int)$magic['records'] ?></strong></span>
        </div>
        <div class="nova-official-reward-item">
          <span class="nova-official-reward-name">活动上传量奖励</span>
          <strong class="nova-official-reward-value upload"><?= htmlspecialchars(mksize((int)$upload['total'])) ?></strong>
          <span class="nova-official-reward-count">发放次数<strong><?= (int)$upload['records'] ?></strong></span>
        </div>
        <div class="nova-official-reward-item">
          <span class="nova-official-reward-name">活动助推奖励</span>
          <strong class="nova-official-reward-value boost"><?= number_format((float)$boost['total'], 2) ?> 魔力</strong>
          <span class="nova-official-reward-count">发放次数<strong><?= (int)$boost['records'] ?></strong></span>
        </div>
      </div>
    </section>
    <?php
    return (string)ob_get_clean();
}

function nova_official_seed_render_details_notice(int $torrentId): string
{
    if (!nova_official_seed_is_active($torrentId)) {
        return '';
    }
    return '<div class="nova-official-details-notice" style="margin:10px auto 14px;padding:10px 14px;border:1px solid #fecdd3;border-radius:6px;background:#fff1f2;color:#be123c;text-align:center;font-weight:700">官种保种活动进行中：连续保种满15天奖励种子体积20%的上传量，满30天累计奖励50%；断开超过48小时将重新计时。</div>';
}

add_filter('sticky_icon', 'nova_official_seed_sticky_marker', 20, 2);
