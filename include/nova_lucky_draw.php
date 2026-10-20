<?php

/**
 * NovaHD lucky draw. Independently implemented from public feature descriptions.
 */

function nova_draw_is_admin(): bool
{
    return get_user_class() >= UC_ADMINISTRATOR;
}

function nova_draw_defaults(): array
{
    return [
        'enabled' => 'yes',
        'cost_per_draw' => '1000',
        'daily_free_draws' => '0',
        'minimum_class' => (string)UC_USER,
        'vip_compensation' => '10000',
        'duplicate_compensation' => '5000',
        'multi_draw_counts' => '1,10,20,50',
        'homepage_winner_count' => '8',
    ];
}

function nova_draw_settings(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = nova_draw_defaults();
    $result = sql_query('SELECT setting_key, setting_value FROM nova_draw_settings');
    while ($result && ($row = mysql_fetch_assoc($result))) {
        $cache[$row['setting_key']] = $row['setting_value'];
    }
    return $cache;
}

function nova_draw_setting(string $key, mixed $default = null): mixed
{
    $settings = nova_draw_settings();
    return $settings[$key] ?? $default;
}

function nova_draw_multi_counts(): array
{
    $counts = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)nova_draw_setting('multi_draw_counts', '1,10,20,50'))))));
    $counts = array_values(array_filter($counts, static fn (int $count) => $count >= 1 && $count <= 50));
    if (!in_array(1, $counts, true)) {
        array_unshift($counts, 1);
    }
    sort($counts);
    return $counts;
}

function nova_draw_token(int $userId, string $action): string
{
    return hash_hmac('sha256', $userId . '|' . $action . '|' . date('Y-m-d'), (string)config('app.key'));
}

function nova_draw_verify_token(string $token, int $userId, string $action): bool
{
    return $token !== '' && hash_equals(nova_draw_token($userId, $action), $token);
}

function nova_draw_prizes(bool $enabledOnly = true): array
{
    $where = $enabledOnly ? "WHERE enabled = 1 AND weight > 0 AND (stock = -1 OR stock > 0)" : '';
    $result = sql_query("SELECT * FROM nova_draw_prizes {$where} ORDER BY sort_order ASC, id ASC");
    $rows = [];
    while ($result && ($row = mysql_fetch_assoc($result))) {
        $rows[] = $row;
    }
    return $rows;
}

function nova_draw_today_count(int $userId): int
{
    $result = sql_query("SELECT COUNT(*) AS total FROM nova_draw_records WHERE user_id = {$userId} AND created_at >= CURDATE()");
    $row = $result ? mysql_fetch_assoc($result) : null;
    return (int)($row['total'] ?? 0);
}

function nova_draw_user_history(int $userId, int $limit = 30): array
{
    $limit = max(1, min(100, $limit));
    $result = sql_query("SELECT prize_name, result_text, cost_bonus, is_win, created_at FROM nova_draw_records WHERE user_id = {$userId} ORDER BY id DESC LIMIT {$limit}");
    $rows = [];
    while ($result && ($row = mysql_fetch_assoc($result))) {
        $rows[] = $row;
    }
    return $rows;
}

function nova_draw_recent_winners(int $limit = 12): array
{
    $limit = max(1, min(50, $limit));
    $result = sql_query("SELECT r.user_id, r.prize_name, r.result_text, r.created_at, u.username
        FROM nova_draw_records r LEFT JOIN users u ON u.id = r.user_id
        WHERE r.is_win = 1 ORDER BY r.id DESC LIMIT {$limit}");
    $rows = [];
    while ($result && ($row = mysql_fetch_assoc($result))) {
        $rows[] = $row;
    }
    return $rows;
}

function nova_draw_bonus_change(array &$user, float $delta, string $comment): void
{
    $old = (float)$user['seedbonus'];
    $expected = round($old + $delta, 1);
    if ($expected < 0) {
        throw new RuntimeException('魔力值不足。');
    }
    $userId = (int)$user['id'];
    $deltaSql = number_format($delta, 1, '.', '');
    $minimumSql = number_format(max(0, -$delta), 1, '.', '');
    sql_query("UPDATE users
        SET seedbonus = ROUND(seedbonus + ({$deltaSql}), 1)
        WHERE id = {$userId} AND seedbonus >= {$minimumSql}") or throw new RuntimeException('无法更新魔力值。');
    if (mysql_affected_rows() !== 1) {
        throw new RuntimeException($delta < 0 ? '魔力值不足。' : '无法更新魔力值。');
    }

    $balanceResult = sql_query("SELECT seedbonus FROM users WHERE id = {$userId} LIMIT 1");
    $balanceRow = $balanceResult ? mysql_fetch_assoc($balanceResult) : null;
    if (!$balanceRow) {
        throw new RuntimeException('无法读取最新魔力值。');
    }
    $new = (float)$balanceRow['seedbonus'];
    \App\Models\BonusLogs::add($userId, $old, $delta, $new, $comment, \App\Models\BonusLogs::BUSINESS_TYPE_LUCKY_DRAW);
    $user['seedbonus'] = $new;
}

function nova_draw_compensate(array &$user, float $amount, string $reason): array
{
    nova_draw_bonus_change($user, $amount, $reason);
    return ['is_win' => 1, 'text' => $reason . '，补偿 ' . number_format($amount, 1) . ' 魔力'];
}

function nova_draw_grant(array &$user, array $prize): array
{
    $userId = (int)$user['id'];
    $value = (int)$prize['value'];
    $name = (string)$prize['name'];
    $type = (string)$prize['type'];
    if ($type === 'nothing') {
        return ['is_win' => 0, 'text' => $name];
    }
    if ($type === 'bonus') {
        nova_draw_bonus_change($user, $value, "幸运抽奖获得 {$name}");
        return ['is_win' => 1, 'text' => '获得 ' . number_format($value) . ' 魔力'];
    }
    if ($type === 'upload') {
        sql_query("UPDATE users SET uploaded = uploaded + {$value} WHERE id = {$userId}") or throw new RuntimeException('无法发放上传量。');
        return ['is_win' => 1, 'text' => '获得 ' . mksize($value) . ' 上传量'];
    }
    if ($type === 'invite_perm') {
        sql_query("UPDATE users SET invites = invites + {$value} WHERE id = {$userId}") or throw new RuntimeException('无法发放邀请。');
        return ['is_win' => 1, 'text' => "获得 {$value} 个永久邀请"];
    }
    if ($type === 'invite_temp') {
        for ($i = 0; $i < max(1, $value); $i++) {
            $hash = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', TIMENOW + 7 * 86400);
            sql_query("INSERT INTO invites (inviter, invitee, hash, valid, expired_at, created_at) VALUES ({$userId}, '', " . sqlesc($hash) . ", 0, " . sqlesc($expires) . ', NOW())') or throw new RuntimeException('无法发放临时邀请。');
        }
        return ['is_win' => 1, 'text' => '获得 ' . max(1, $value) . ' 个临时邀请（7 天有效）'];
    }
    if ($type === 'attendance_card') {
        sql_query("UPDATE users SET attendance_card = attendance_card + {$value} WHERE id = {$userId}") or throw new RuntimeException('无法发放补签卡。');
        return ['is_win' => 1, 'text' => "获得 {$value} 张补签卡"];
    }
    if ($type === 'vip_days') {
        if ((int)$user['class'] >= UC_VIP) {
            return nova_draw_compensate($user, (float)nova_draw_setting('vip_compensation', 10000), "{$name}（当前等级无需重复发放）");
        }
        $until = date('Y-m-d H:i:s', TIMENOW + max(1, $value) * 86400);
        sql_query("UPDATE users SET class = " . UC_VIP . ", vip_added = 'yes', vip_until = " . sqlesc($until) . " WHERE id = {$userId}") or throw new RuntimeException('无法发放 VIP。');
        $user['class'] = UC_VIP;
        return ['is_win' => 1, 'text' => '获得 VIP ' . max(1, $value) . ' 天'];
    }
    if ($type === 'rainbow_id_days') {
        $days = max(1, $value);
        $metaResult = sql_query("SELECT id, deadline FROM user_metas WHERE uid = {$userId} AND meta_key = 'PERSONALIZED_USERNAME' LIMIT 1 FOR UPDATE");
        $meta = $metaResult ? mysql_fetch_assoc($metaResult) : null;
        if ($meta && $meta['deadline'] === null) {
            return nova_draw_compensate($user, (float)nova_draw_setting('duplicate_compensation', 5000), "{$name}（彩虹 ID 已永久有效）");
        }
        $base = $meta && !empty($meta['deadline']) && strtotime($meta['deadline']) > TIMENOW ? strtotime($meta['deadline']) : TIMENOW;
        $deadline = date('Y-m-d H:i:s', $base + $days * 86400);
        if ($meta) {
            sql_query('UPDATE user_metas SET deadline = ' . sqlesc($deadline) . ", status = 0, updated_at = NOW() WHERE id = " . intval($meta['id']));
        } else {
            sql_query("INSERT INTO user_metas (uid, meta_key, status, deadline, created_at, updated_at) VALUES ({$userId}, 'PERSONALIZED_USERNAME', 0, " . sqlesc($deadline) . ', NOW(), NOW())');
        }
        return ['is_win' => 1, 'text' => "获得彩虹 ID {$days} 天"];
    }
    if ($type === 'rename_card') {
        $metaResult = sql_query("SELECT id FROM user_metas WHERE uid = {$userId} AND meta_key = 'CHANGE_USERNAME' AND status = 0 LIMIT 1 FOR UPDATE");
        if ($metaResult && mysql_num_rows($metaResult)) {
            return nova_draw_compensate($user, (float)nova_draw_setting('duplicate_compensation', 5000), "{$name}（改名卡已持有）");
        }
        sql_query("INSERT INTO user_metas (uid, meta_key, status, deadline, created_at, updated_at) VALUES ({$userId}, 'CHANGE_USERNAME', 0, NULL, NOW(), NOW())") or throw new RuntimeException('无法发放改名卡。');
        return ['is_win' => 1, 'text' => '获得 1 张改名卡'];
    }
    if ($type === 'medal') {
        $medalId = (int)$prize['medal_id'];
        $medalResult = sql_query("SELECT id, name, duration, priority FROM medals WHERE id = {$medalId} LIMIT 1 FOR UPDATE");
        $medal = $medalResult ? mysql_fetch_assoc($medalResult) : null;
        if (!$medal) {
            throw new RuntimeException('配置的勋章不存在。');
        }
        $ownedResult = sql_query("SELECT id FROM user_medals WHERE uid = {$userId} AND medal_id = {$medalId} AND (expire_at IS NULL OR expire_at >= NOW()) LIMIT 1 FOR UPDATE");
        if ($ownedResult && mysql_num_rows($ownedResult)) {
            return nova_draw_compensate($user, (float)nova_draw_setting('duplicate_compensation', 5000), $medal['name'] . '（已拥有）');
        }
        $expireAt = !empty($medal['duration']) ? sqlesc(date('Y-m-d H:i:s', TIMENOW + (int)$medal['duration'] * 86400)) : 'NULL';
        sql_query("INSERT INTO user_medals (uid, medal_id, expire_at, status, priority, created_at, updated_at) VALUES ({$userId}, {$medalId}, {$expireAt}, 1, " . intval($medal['priority']) . ', NOW(), NOW())') or throw new RuntimeException('无法发放勋章。');
        return ['is_win' => 1, 'text' => '获得勋章：' . $medal['name']];
    }
    throw new RuntimeException('不支持的奖品类型。');
}

function nova_draw_pick_prize(array $prizes): array
{
    $total = 0;
    foreach ($prizes as $prize) {
        $total += max(1, (int)round((float)$prize['weight'] * 1000));
    }
    if ($total < 1) {
        throw new RuntimeException('奖池权重无效。');
    }
    $hit = random_int(1, $total);
    foreach ($prizes as $prize) {
        $hit -= max(1, (int)round((float)$prize['weight'] * 1000));
        if ($hit <= 0) {
            return $prize;
        }
    }
    return end($prizes);
}

function nova_draw_spin(int $userId, int $count, string $ip, bool $commit = true): array
{
    if ((string)nova_draw_setting('enabled', 'yes') !== 'yes') {
        throw new RuntimeException('幸运抽奖暂未开放。');
    }
    if (!in_array($count, nova_draw_multi_counts(), true)) {
        throw new InvalidArgumentException('不支持的抽奖次数。');
    }
    sql_query('START TRANSACTION');
    try {
        $userResult = sql_query("SELECT id, username, class, seedbonus FROM users WHERE id = {$userId} LIMIT 1 FOR UPDATE");
        $user = $userResult ? mysql_fetch_assoc($userResult) : null;
        if (!$user) {
            throw new RuntimeException('用户不存在。');
        }
        if ((int)$user['class'] < (int)nova_draw_setting('minimum_class', UC_USER)) {
            throw new RuntimeException('当前等级暂不能参与抽奖。');
        }
        $todayResult = sql_query("SELECT COUNT(*) AS total FROM nova_draw_records WHERE user_id = {$userId} AND created_at >= CURDATE() FOR UPDATE");
        $todayRow = $todayResult ? mysql_fetch_assoc($todayResult) : null;
        $freeLeft = max(0, (int)nova_draw_setting('daily_free_draws', 0) - (int)($todayRow['total'] ?? 0));
        $paidCount = max(0, $count - $freeLeft);
        $totalCost = $paidCount * max(0, (float)nova_draw_setting('cost_per_draw', 1000));
        if ((float)$user['seedbonus'] < $totalCost) {
            throw new RuntimeException('魔力值不足，本次需要 ' . number_format($totalCost, 1) . '。');
        }
        if ($totalCost > 0) {
            nova_draw_bonus_change($user, -$totalCost, "幸运抽奖 {$count} 次");
        }
        $balanceAfterCost = (float)$user['seedbonus'];
        $result = sql_query("SELECT * FROM nova_draw_prizes WHERE enabled = 1 AND weight > 0 AND (stock = -1 OR stock > 0) ORDER BY sort_order ASC, id ASC FOR UPDATE");
        $prizes = [];
        while ($result && ($row = mysql_fetch_assoc($result))) {
            $prizes[] = $row;
        }
        if (!$prizes) {
            throw new RuntimeException('当前奖池为空，请联系管理员。');
        }
        $draws = [];
        for ($i = 0; $i < $count; $i++) {
            $available = array_values(array_filter($prizes, static fn (array $prize) => (int)$prize['stock'] === -1 || (int)$prize['stock'] > 0));
            if (!$available) {
                throw new RuntimeException('奖池库存不足。');
            }
            $prize = nova_draw_pick_prize($available);
            foreach ($prizes as &$candidate) {
                if ((int)$candidate['id'] === (int)$prize['id'] && (int)$candidate['stock'] > 0) {
                    $candidate['stock'] = (int)$candidate['stock'] - 1;
                    sql_query('UPDATE nova_draw_prizes SET stock = stock - 1, updated_at = NOW() WHERE id = ' . (int)$candidate['id'] . ' AND stock > 0') or throw new RuntimeException('无法扣减奖品库存。');
                    break;
                }
            }
            unset($candidate);
            $grant = nova_draw_grant($user, $prize);
            $cost = $i < min($count, $freeLeft) ? 0 : (float)nova_draw_setting('cost_per_draw', 1000);
            sql_query("INSERT INTO nova_draw_records (user_id, prize_id, prize_name, prize_type, prize_value, is_win, cost_bonus, result_text, ip, created_at)
                VALUES ({$userId}, " . (int)$prize['id'] . ', ' . sqlesc($prize['name']) . ', ' . sqlesc($prize['type']) . ', ' . (int)$prize['value'] . ', ' . (int)$grant['is_win'] . ', ' . sqlesc($cost) . ', ' . sqlesc($grant['text']) . ', ' . sqlesc($ip) . ', NOW())') or throw new RuntimeException('无法保存抽奖记录。');
            $draws[] = [
                'prize_id' => (int)$prize['id'],
                'name' => $prize['name'],
                'color' => $prize['color'],
                'is_win' => (bool)$grant['is_win'],
                'result' => $grant['text'],
            ];
        }
        sql_query($commit ? 'COMMIT' : 'ROLLBACK');
        return [
            'draws' => $draws,
            'balance' => (float)$user['seedbonus'],
            'balance_after_cost' => $balanceAfterCost,
            'total_cost' => $totalCost,
            'free_used' => min($count, $freeLeft),
            'rolled_back' => !$commit,
        ];
    } catch (Throwable $e) {
        sql_query('ROLLBACK');
        throw $e;
    }
}

function nova_draw_home_widget(): string
{
    if ((string)nova_draw_setting('enabled', 'yes') !== 'yes') {
        return '';
    }
    $rows = nova_draw_recent_winners((int)nova_draw_setting('homepage_winner_count', 8));
    ob_start();
    ?>
    <section class="nova-draw-home" data-nova-draw-widget>
      <header><div><span>LUCKY DRAW</span><h2>幸运抽奖中奖公示</h2></div><a href="fortune-wheel.php">进入抽奖</a></header>
      <div class="nova-draw-home-list">
        <?php if (!$rows): ?><p class="nova-draw-home-empty">还没有中奖记录，下一位幸运用户可能就是你。</p><?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <div><b><?php echo htmlspecialchars($row['username'] ?: ('UID ' . $row['user_id'])); ?></b><span><?php echo htmlspecialchars($row['result_text'] ?: $row['prize_name']); ?></span><time><?php echo htmlspecialchars(date('m-d H:i', strtotime($row['created_at']))); ?></time></div>
        <?php endforeach; ?>
      </div>
    </section>
    <style>
      .nova-draw-home{width:65%;margin:18px auto;background:#fff;border:1px solid #cae9e7;border-radius:8px;overflow:hidden;box-shadow:0 8px 24px rgba(22,94,94,.07)}
      .nova-draw-home header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:linear-gradient(90deg,#e9faf8,#fff8ed)}
      .nova-draw-home header span{display:block;color:#118b83;font-size:11px;font-weight:900;letter-spacing:1.5px}.nova-draw-home h2{margin:2px 0 0!important;color:#244f55;font-size:18px!important}
      .nova-draw-home header a{padding:8px 14px;border-radius:6px;background:#ed8b36;color:#fff!important;font-weight:800;text-decoration:none}
      .nova-draw-home-list{display:grid;grid-template-columns:1fr 1fr;padding:5px 18px 13px}.nova-draw-home-list>div{display:grid;grid-template-columns:100px 1fr auto;gap:10px;align-items:center;padding:10px 8px;border-bottom:1px solid #edf5f4;color:#577078}
      .nova-draw-home-list b{color:#137d77;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.nova-draw-home-list span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.nova-draw-home-list time{color:#91a4a8;font-size:12px}.nova-draw-home-empty{grid-column:1/-1;text-align:center;color:#819497;padding:18px}
      @media(max-width:1200px){.nova-draw-home{width:92%}}@media(max-width:700px){.nova-draw-home{width:calc(100% - 18px)}.nova-draw-home-list{grid-template-columns:1fr}.nova-draw-home-list>div{grid-template-columns:80px 1fr}.nova-draw-home-list time{display:none}}
    </style>
    <?php
    return (string)ob_get_clean();
}
