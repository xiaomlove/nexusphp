<?php

/**
 * NovaHD paid torrent promotion.
 * Independently implemented from the public product description.
 */

function nova_sticky_config(): array
{
    return [
        'base_hourly_cost' => 100.0,
        'min_hours' => 1,
        'max_hours' => 168,
        'max_winners' => 20,
        'ranking_limit' => 50,
    ];
}

function nova_sticky_token(int $torrentId, string $action, int $userId): string
{
    return hash_hmac('sha256', $torrentId . '|' . $action . '|' . $userId, (string)config('app.key'));
}

function nova_sticky_verify_token(string $token, int $torrentId, string $action, int $userId): bool
{
    return $token !== '' && hash_equals(nova_sticky_token($torrentId, $action, $userId), $token);
}

function nova_sticky_active_campaign(int $torrentId): ?array
{
    $result = sql_query("SELECT * FROM nova_sticky_promotions WHERE torrent_id = {$torrentId} AND status = 'active' AND ends_at > NOW() ORDER BY id DESC LIMIT 1");
    $row = $result ? mysql_fetch_assoc($result) : false;
    return $row ?: null;
}

function nova_sticky_campaign_coefficient(int $torrentId): float
{
    $result = calculate_seed_bonus(0, [$torrentId]);
    return max(1.0, round((float)($result['seed_bonus'] ?? 1), 4));
}

function nova_sticky_occupancy_cost(float $coefficient, int $hours): float
{
    return round($coefficient * (float)nova_sticky_config()['base_hourly_cost'] * $hours, 2);
}

function nova_sticky_rankings(int $campaignId, int $limit = 50): array
{
    $campaignId = intval($campaignId);
    $limit = max(1, min(200, intval($limit)));
    $campaignResult = sql_query("SELECT torrent_id, reward_pool, winner_count FROM nova_sticky_promotions WHERE id = {$campaignId} LIMIT 1");
    $campaign = $campaignResult ? mysql_fetch_assoc($campaignResult) : null;
    if (!$campaign) {
        return [];
    }

    $torrentId = (int)$campaign['torrent_id'];
    $sql = "SELECT s.user_id, u.username, COALESCE(p.is_anonymous, 'no') AS is_anonymous,
                GREATEST(s.current_uploaded - COALESCE(p.baseline_uploaded, 0), 0) AS uploaded_delta
            FROM (
                SELECT userid AS user_id, SUM(uploaded) AS current_uploaded
                FROM snatched WHERE torrentid = {$torrentId} GROUP BY userid
            ) s
            LEFT JOIN nova_sticky_promotion_participants p
                ON p.campaign_id = {$campaignId} AND p.user_id = s.user_id
            LEFT JOIN users u ON u.id = s.user_id
            ORDER BY uploaded_delta DESC, s.user_id ASC
            LIMIT {$limit}";
    $result = sql_query($sql);
    $rows = [];
    while ($result && ($row = mysql_fetch_assoc($result))) {
        $row['uploaded_delta'] = max(0, (float)$row['uploaded_delta']);
        $rows[] = $row;
    }
    $pool = (float)$campaign['reward_pool'];
    $winnerCount = (int)$campaign['winner_count'];
    $total = array_sum(array_column(array_slice($rows, 0, $winnerCount), 'uploaded_delta'));
    foreach ($rows as $index => &$row) {
        $row['rank'] = $index + 1;
        $row['estimated_reward'] = $index < $winnerCount && $total > 0 ? round($pool * $row['uploaded_delta'] / $total, 2) : 0;
    }
    unset($row);
    return $rows;
}

function nova_sticky_user_display(int $userId, bool $anonymous): string
{
    if ($userId === 0) {
        return '<b class="nova-sticky-system">站点系统</b>';
    }
    if ($anonymous) {
        return '<i class="nova-sticky-anonymous">匿名</i>';
    }
    return get_username($userId);
}

function nova_sticky_start_system_official(int $torrentId, float $reward = 3000.0, int $hours = 48, int $winnerCount = 10): ?int
{
    $officialTag = (int)get_setting('bonus.official_tag');
    if ($officialTag < 1) {
        return null;
    }
    $tagResult = sql_query("SELECT id FROM torrent_tags WHERE torrent_id = {$torrentId} AND tag_id = {$officialTag} LIMIT 1");
    if (!$tagResult || mysql_num_rows($tagResult) === 0) {
        return null;
    }

    $hours = max(1, min((int)nova_sticky_config()['max_hours'], $hours));
    $winnerCount = max(1, min((int)nova_sticky_config()['max_winners'], $winnerCount));
    $reward = round(max(0, $reward), 2);
    sql_query('START TRANSACTION');
    try {
        $torrentResult = sql_query("SELECT id, sp_state, promotion_time_type, promotion_until FROM torrents WHERE id = {$torrentId} FOR UPDATE");
        $torrent = $torrentResult ? mysql_fetch_assoc($torrentResult) : null;
        if (!$torrent) {
            throw new RuntimeException('种子不存在。');
        }
        $existingResult = sql_query("SELECT id FROM nova_sticky_promotions WHERE torrent_id = {$torrentId} AND status = 'active' FOR UPDATE");
        if ($existingResult && mysql_num_rows($existingResult) > 0) {
            sql_query('ROLLBACK');
            return null;
        }

        $coefficient = nova_sticky_campaign_coefficient($torrentId);
        $end = date('Y-m-d H:i:s', time() + $hours * 3600);
        $originalUntil = empty($torrent['promotion_until']) ? 'NULL' : sqlesc($torrent['promotion_until']);
        sql_query("INSERT INTO nova_sticky_promotions
            (torrent_id, sponsor_id, sponsor_anonymous, promotion_type, original_sp_state, original_promotion_time_type,
             original_promotion_until, coefficient, base_hourly_rate, occupancy_cost, bid_bonus, reward_pool,
             winner_count, starts_at, ends_at, status, created_at, updated_at)
            VALUES ({$torrentId}, 0, 'no', " . intval($torrent['sp_state']) . ", " . intval($torrent['sp_state']) . ", " . intval($torrent['promotion_time_type']) . ",
             {$originalUntil}, " . sqlesc($coefficient) . ", 0, 0, 0, " . sqlesc($reward) . ",
             {$winnerCount}, NOW(), " . sqlesc($end) . ", 'active', NOW(), NOW())") or throw new RuntimeException('无法创建系统助推奖励。');
        $campaignId = (int)mysql_insert_id();
        sql_query("INSERT INTO nova_sticky_promotion_events
            (campaign_id, user_id, event_type, hours_added, bid_added, reward_added, winner_count_added, created_at)
            VALUES ({$campaignId}, 0, 'start', {$hours}, 0, " . sqlesc($reward) . ", {$winnerCount}, NOW())") or throw new RuntimeException('无法记录系统助推奖励。');
        sql_query('COMMIT');
        publish_model_event(\App\Enums\ModelEventEnum::TORRENT_UPDATED, $torrentId);
        return $campaignId;
    } catch (Throwable $e) {
        sql_query('ROLLBACK');
        throw $e;
    }
}

function nova_sticky_record_bonus(int $userId, float $old, float $delta, string $comment, int $businessType): void
{
    $new = round($old + $delta, 2);
    sql_query("UPDATE users SET seedbonus = " . sqlesc($new) . " WHERE id = {$userId}") or throw new RuntimeException('Unable to update bonus balance.');
    \App\Models\BonusLogs::add($userId, $old, $delta, $new, $comment, $businessType);
}

function nova_sticky_start(int $torrentId, int $userId, array $input): int
{
    $config = nova_sticky_config();
    $hours = intval($input['hours'] ?? 0);
    $bid = round(max(0, (float)($input['bid_bonus'] ?? 0)), 2);
    $reward = round(max(0, (float)($input['reward_pool'] ?? 0)), 2);
    $winnerCount = intval($input['winner_count'] ?? 0);
    $promotionType = intval($input['promotion_type'] ?? 0);
    $sponsorAnonymous = ($input['anonymous'] ?? 'no') === 'yes' ? 'yes' : 'no';
    if ($hours < $config['min_hours'] || $hours > $config['max_hours']) {
        throw new InvalidArgumentException('助推时长必须为 1 至 168 小时。');
    }
    if (!in_array($promotionType, [2, 3, 4], true)) {
        throw new InvalidArgumentException('请选择有效的促销类型。');
    }
    if ($winnerCount < 1 || $winnerCount > $config['max_winners']) {
        throw new InvalidArgumentException('奖励人数必须为 1 至 20 人。');
    }

    sql_query('START TRANSACTION');
    try {
        $torrentResult = sql_query("SELECT id, sp_state, promotion_time_type, promotion_until FROM torrents WHERE id = {$torrentId} FOR UPDATE");
        $torrent = $torrentResult ? mysql_fetch_assoc($torrentResult) : null;
        if (!$torrent) {
            throw new RuntimeException('种子不存在。');
        }
        $activeResult = sql_query("SELECT id FROM nova_sticky_promotions WHERE torrent_id = {$torrentId} AND status = 'active' FOR UPDATE");
        if ($activeResult && mysql_num_rows($activeResult) > 0) {
            throw new RuntimeException('此资源已有正在进行的助推。');
        }
        $userResult = sql_query("SELECT seedbonus FROM users WHERE id = {$userId} FOR UPDATE");
        $user = $userResult ? mysql_fetch_assoc($userResult) : null;
        if (!$user) {
            throw new RuntimeException('用户不存在。');
        }
        $coefficient = nova_sticky_campaign_coefficient($torrentId);
        $occupancy = nova_sticky_occupancy_cost($coefficient, $hours);
        $total = round($occupancy + $bid + $reward, 2);
        $balance = (float)$user['seedbonus'];
        if ($total <= 0 || $balance < $total) {
            throw new RuntimeException('魔力值不足，需要 ' . number_format($total, 2) . '。');
        }

        $end = date('Y-m-d H:i:s', time() + $hours * 3600);
        $originalUntil = empty($torrent['promotion_until']) ? 'NULL' : sqlesc($torrent['promotion_until']);
        $insert = "INSERT INTO nova_sticky_promotions
            (torrent_id, sponsor_id, sponsor_anonymous, promotion_type, original_sp_state, original_promotion_time_type,
             original_promotion_until, coefficient, base_hourly_rate, occupancy_cost, bid_bonus,
             reward_pool, winner_count, starts_at, ends_at, status, created_at, updated_at)
            VALUES ({$torrentId}, {$userId}, " . sqlesc($sponsorAnonymous) . ", {$promotionType}, " . intval($torrent['sp_state']) . ", " . intval($torrent['promotion_time_type']) . ",
             {$originalUntil}, " . sqlesc($coefficient) . ", " . sqlesc($config['base_hourly_cost']) . ", " . sqlesc($occupancy) . ", " . sqlesc($bid) . ",
             " . sqlesc($reward) . ", {$winnerCount}, NOW(), " . sqlesc($end) . ", 'active', NOW(), NOW())";
        sql_query($insert) or throw new RuntimeException('无法创建助推。');
        $campaignId = (int)mysql_insert_id();

        sql_query("INSERT INTO nova_sticky_promotion_participants
            (campaign_id, user_id, baseline_uploaded, final_uploaded, reward, is_anonymous, created_at, updated_at)
            SELECT {$campaignId}, userid, SUM(uploaded), 0, 0, 'no', NOW(), NOW()
            FROM snatched WHERE torrentid = {$torrentId} GROUP BY userid") or throw new RuntimeException('无法建立上传量快照。');
        sql_query("UPDATE torrents SET sp_state = {$promotionType}, promotion_time_type = 2, promotion_until = " . sqlesc($end) . " WHERE id = {$torrentId}") or throw new RuntimeException('无法应用促销状态。');
        nova_sticky_record_bonus($userId, $balance, -$total, "种子 #{$torrentId} 付费助推：占位 {$occupancy}，竞价 {$bid}，奖励池 {$reward}", \App\Models\BonusLogs::BUSINESS_TYPE_STICKY_PROMOTION);
        sql_query("INSERT INTO nova_sticky_promotion_events (campaign_id, user_id, event_type, hours_added, bid_added, reward_added, winner_count_added, created_at)
            VALUES ({$campaignId}, {$userId}, 'start', {$hours}, " . sqlesc($bid) . ", " . sqlesc($reward) . ", {$winnerCount}, NOW())");
        sql_query('COMMIT');
        publish_model_event(\App\Enums\ModelEventEnum::TORRENT_UPDATED, $torrentId);
        return $campaignId;
    } catch (Throwable $e) {
        sql_query('ROLLBACK');
        throw $e;
    }
}

function nova_sticky_set_anonymous(int $torrentId, int $userId, bool $anonymous): void
{
    sql_query('START TRANSACTION');
    try {
        $campaignResult = sql_query("SELECT id, sponsor_id FROM nova_sticky_promotions WHERE torrent_id = {$torrentId} AND status = 'active' AND ends_at > NOW() FOR UPDATE");
        $campaign = $campaignResult ? mysql_fetch_assoc($campaignResult) : null;
        if (!$campaign) {
            throw new RuntimeException('当前没有进行中的助推活动。');
        }
        $campaignId = (int)$campaign['id'];
        $anonymousValue = $anonymous ? 'yes' : 'no';
        sql_query("INSERT INTO nova_sticky_promotion_participants
            (campaign_id, user_id, baseline_uploaded, final_uploaded, reward, is_anonymous, created_at, updated_at)
            VALUES ({$campaignId}, {$userId}, 0, 0, 0, " . sqlesc($anonymousValue) . ", NOW(), NOW())
            ON DUPLICATE KEY UPDATE is_anonymous = VALUES(is_anonymous), updated_at = NOW()") or throw new RuntimeException('无法保存匿名设置。');
        if ((int)$campaign['sponsor_id'] === $userId) {
            sql_query("UPDATE nova_sticky_promotions SET sponsor_anonymous = " . sqlesc($anonymousValue) . ", updated_at = NOW() WHERE id = {$campaignId}");
        }
        sql_query('COMMIT');
    } catch (Throwable $e) {
        sql_query('ROLLBACK');
        throw $e;
    }
}

function nova_sticky_append(int $torrentId, int $userId, array $input): int
{
    $config = nova_sticky_config();
    $hours = max(0, intval($input['hours'] ?? 0));
    $bid = round(max(0, (float)($input['bid_bonus'] ?? 0)), 2);
    $reward = round(max(0, (float)($input['reward_pool'] ?? 0)), 2);
    $winnerAdd = max(0, intval($input['winner_count_add'] ?? 0));
    if ($hours > $config['max_hours'] || $winnerAdd > $config['max_winners']) {
        throw new InvalidArgumentException('追加数值超出允许范围。');
    }
    if ($hours === 0 && $bid <= 0 && $reward <= 0 && $winnerAdd === 0) {
        throw new InvalidArgumentException('请至少填写一项追加内容。');
    }

    sql_query('START TRANSACTION');
    try {
        $campaignResult = sql_query("SELECT * FROM nova_sticky_promotions WHERE torrent_id = {$torrentId} AND status = 'active' FOR UPDATE");
        $campaign = $campaignResult ? mysql_fetch_assoc($campaignResult) : null;
        if (!$campaign || strtotime($campaign['ends_at']) <= time()) {
            throw new RuntimeException('助推已结束，无法追加。');
        }
        if ((int)$campaign['sponsor_id'] !== $userId) {
            throw new RuntimeException('只有发起人可以追加助推。');
        }
        if ((int)$campaign['winner_count'] + $winnerAdd > $config['max_winners']) {
            throw new RuntimeException('奖励人数最多为 20 人。');
        }
        $userResult = sql_query("SELECT seedbonus FROM users WHERE id = {$userId} FOR UPDATE");
        $user = $userResult ? mysql_fetch_assoc($userResult) : null;
        $occupancy = nova_sticky_occupancy_cost((float)$campaign['coefficient'], $hours);
        $total = round($occupancy + $bid + $reward, 2);
        $balance = (float)($user['seedbonus'] ?? 0);
        if ($balance < $total) {
            throw new RuntimeException('魔力值不足，需要 ' . number_format($total, 2) . '。');
        }
        $baseTime = max(time(), strtotime($campaign['ends_at']));
        $newEnd = date('Y-m-d H:i:s', $baseTime + $hours * 3600);
        $campaignId = (int)$campaign['id'];
        sql_query("UPDATE nova_sticky_promotions SET
            occupancy_cost = occupancy_cost + " . sqlesc($occupancy) . ",
            bid_bonus = bid_bonus + " . sqlesc($bid) . ", reward_pool = reward_pool + " . sqlesc($reward) . ",
            winner_count = winner_count + {$winnerAdd}, ends_at = " . sqlesc($newEnd) . ", updated_at = NOW()
            WHERE id = {$campaignId}") or throw new RuntimeException('无法追加助推。');
        sql_query("UPDATE torrents SET sp_state = " . intval($campaign['promotion_type']) . ", promotion_time_type = 2, promotion_until = " . sqlesc($newEnd) . " WHERE id = {$torrentId}");
        if ($total > 0) {
            nova_sticky_record_bonus($userId, $balance, -$total, "种子 #{$torrentId} 追加助推：占位 {$occupancy}，竞价 {$bid}，奖励池 {$reward}", \App\Models\BonusLogs::BUSINESS_TYPE_STICKY_PROMOTION);
        }
        sql_query("INSERT INTO nova_sticky_promotion_events (campaign_id, user_id, event_type, hours_added, bid_added, reward_added, winner_count_added, created_at)
            VALUES ({$campaignId}, {$userId}, 'append', {$hours}, " . sqlesc($bid) . ", " . sqlesc($reward) . ", {$winnerAdd}, NOW())");
        sql_query('COMMIT');
        publish_model_event(\App\Enums\ModelEventEnum::TORRENT_UPDATED, $torrentId);
        return $campaignId;
    } catch (Throwable $e) {
        sql_query('ROLLBACK');
        throw $e;
    }
}

function nova_sticky_send_message(int $receiver, string $subject, string $message): void
{
    sql_query("INSERT INTO messages (sender, receiver, added, subject, msg, unread, location, saved)
        VALUES (0, {$receiver}, NOW(), " . sqlesc($subject) . ", " . sqlesc($message) . ", 'yes', 1, 'no')");
}

function nova_sticky_settle_expired(): int
{
    $ids = [];
    $result = sql_query("SELECT id FROM nova_sticky_promotions WHERE status = 'active' AND ends_at <= NOW() ORDER BY id ASC LIMIT 50");
    while ($result && ($row = mysql_fetch_assoc($result))) {
        $ids[] = (int)$row['id'];
    }
    $settled = 0;
    foreach ($ids as $campaignId) {
        sql_query('START TRANSACTION');
        try {
            $campaignResult = sql_query("SELECT * FROM nova_sticky_promotions WHERE id = {$campaignId} FOR UPDATE");
            $campaign = $campaignResult ? mysql_fetch_assoc($campaignResult) : null;
            if (!$campaign || $campaign['status'] !== 'active' || strtotime($campaign['ends_at']) > time()) {
                sql_query('ROLLBACK');
                continue;
            }
            $torrentId = (int)$campaign['torrent_id'];
            sql_query("INSERT IGNORE INTO nova_sticky_promotion_participants
                (campaign_id, user_id, baseline_uploaded, final_uploaded, reward, is_anonymous, created_at, updated_at)
                SELECT {$campaignId}, userid, 0, 0, 0, 'no', NOW(), NOW() FROM snatched WHERE torrentid = {$torrentId} GROUP BY userid");
            $rankings = nova_sticky_rankings($campaignId, 200);
            $eligible = array_values(array_filter($rankings, static fn(array $row): bool => $row['uploaded_delta'] > 0));
            $winners = array_slice($eligible, 0, (int)$campaign['winner_count']);
            $winnerUpload = array_sum(array_column($winners, 'uploaded_delta'));
            $remaining = (float)$campaign['reward_pool'];
            foreach ($winners as $index => $winner) {
                $reward = $index === count($winners) - 1
                    ? round($remaining, 2)
                    : round((float)$campaign['reward_pool'] * $winner['uploaded_delta'] / $winnerUpload, 2);
                $remaining -= $reward;
                $winnerId = (int)$winner['user_id'];
                $userResult = sql_query("SELECT seedbonus FROM users WHERE id = {$winnerId} FOR UPDATE");
                $user = $userResult ? mysql_fetch_assoc($userResult) : null;
                if ($user && $reward > 0) {
                    nova_sticky_record_bonus($winnerId, (float)$user['seedbonus'], $reward, "种子 #{$torrentId} 助推上传奖励", \App\Models\BonusLogs::BUSINESS_TYPE_RECEIVE_REWARD);
                    nova_sticky_send_message($winnerId, '助推奖励已发放', "您在种子 #{$torrentId} 的助推活动中排名第 " . ($index + 1) . "，获得 " . number_format($reward, 2) . " 魔力值。");
                }
                sql_query("UPDATE nova_sticky_promotion_participants SET final_uploaded = " . sqlesc($winner['uploaded_delta']) . ", reward = " . sqlesc($reward) . ", updated_at = NOW()
                    WHERE campaign_id = {$campaignId} AND user_id = {$winnerId}");
            }
            if (!$winners && (float)$campaign['reward_pool'] > 0 && (int)$campaign['sponsor_id'] > 0) {
                $sponsorId = (int)$campaign['sponsor_id'];
                $userResult = sql_query("SELECT seedbonus FROM users WHERE id = {$sponsorId} FOR UPDATE");
                $user = $userResult ? mysql_fetch_assoc($userResult) : null;
                if ($user) {
                    nova_sticky_record_bonus($sponsorId, (float)$user['seedbonus'], (float)$campaign['reward_pool'], "种子 #{$torrentId} 助推无人产生上传量，退回奖励池", \App\Models\BonusLogs::BUSINESS_TYPE_RECEIVE_REWARD);
                }
            }
            $torrentResult = sql_query("SELECT sp_state, promotion_time_type, promotion_until FROM torrents WHERE id = {$torrentId} FOR UPDATE");
            $torrent = $torrentResult ? mysql_fetch_assoc($torrentResult) : null;
            $campaignStillOwnsPromotion = $torrent
                && (int)$torrent['sp_state'] === (int)$campaign['promotion_type']
                && (int)$torrent['promotion_time_type'] === 2
                && strtotime((string)$torrent['promotion_until']) === strtotime((string)$campaign['ends_at']);
            if ($campaignStillOwnsPromotion) {
                $originalUntil = empty($campaign['original_promotion_until']) ? 'NULL' : sqlesc($campaign['original_promotion_until']);
                sql_query("UPDATE torrents SET sp_state = " . intval($campaign['original_sp_state']) . ", promotion_time_type = " . intval($campaign['original_promotion_time_type']) . ", promotion_until = {$originalUntil} WHERE id = {$torrentId}");
            }
            sql_query("UPDATE nova_sticky_promotions SET status = 'settled', settled_at = NOW(), updated_at = NOW() WHERE id = {$campaignId}");
            if ((int)$campaign['sponsor_id'] > 0) {
            }
            sql_query('COMMIT');
            publish_model_event(\App\Enums\ModelEventEnum::TORRENT_UPDATED, $torrentId);
            $settled++;
        } catch (Throwable $e) {
            sql_query('ROLLBACK');
            do_log('[NOVA_STICKY_SETTLE] campaign ' . $campaignId . ': ' . $e->getMessage(), 'error');
        }
    }
    return $settled;
}

function nova_sticky_render_rankings(array $campaign): string
{
    $rankings = nova_sticky_rankings((int)$campaign['id'], nova_sticky_config()['ranking_limit']);
    $html = '<div class="nova-sticky-ranking"><table><thead><tr><th>排名</th><th>用户</th><th>活动上传量</th><th>预计奖励</th></tr></thead><tbody id="nova-sticky-ranking-body">';
    if (!$rankings) {
        $html .= '<tr><td colspan="4">暂无上传记录</td></tr>';
    } else {
        foreach ($rankings as $item) {
            $html .= '<tr><td>' . intval($item['rank']) . '</td><td>' . nova_sticky_user_display((int)$item['user_id'], ($item['is_anonymous'] ?? 'no') === 'yes') . '</td><td>' . mksize((float)$item['uploaded_delta']) . '</td><td>' . number_format((float)$item['estimated_reward'], 2) . '</td></tr>';
        }
    }
    return $html . '</tbody></table></div>';
}

function nova_sticky_render_detail(array $torrent, array $currentUser): void
{
    $torrentId = (int)$torrent['id'];
    $userId = (int)$currentUser['id'];
    $campaign = nova_sticky_active_campaign($torrentId);
    $coefficient = $campaign ? (float)$campaign['coefficient'] : nova_sticky_campaign_coefficient($torrentId);
    $baseRate = (float)nova_sticky_config()['base_hourly_cost'];
    $success = !empty($_GET['nova_sticky_success']);
    $viewerAnonymous = false;
    if ($campaign) {
        if ((int)$campaign['sponsor_id'] === $userId) {
            $viewerAnonymous = ($campaign['sponsor_anonymous'] ?? 'no') === 'yes';
        } else {
            $privacyResult = sql_query("SELECT is_anonymous FROM nova_sticky_promotion_participants WHERE campaign_id = " . intval($campaign['id']) . " AND user_id = {$userId} LIMIT 1");
            $privacyRow = $privacyResult ? mysql_fetch_assoc($privacyResult) : null;
            $viewerAnonymous = ($privacyRow['is_anonymous'] ?? 'no') === 'yes';
        }
    }
    ?>
    <tr class="nova-sticky-detail-row"><td colspan="2" style="padding:0;border:0;background:transparent">
    <style>
    .nova-sticky-panel{width:65%;min-width:920px;box-sizing:border-box;margin:16px auto 20px;border:1px solid rgba(13,148,136,.42);border-radius:8px;overflow:hidden;background:linear-gradient(135deg,rgba(240,253,250,.98),rgba(239,246,255,.98));color:#17333b;box-shadow:0 12px 30px rgba(13,78,74,.14)}
    .nova-sticky-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;background:linear-gradient(90deg,rgba(20,184,166,.18),rgba(255,255,255,.82) 58%,rgba(249,115,22,.10));border-bottom:1px solid rgba(13,148,136,.22)}
    .nova-sticky-head strong{font-size:16px;color:#0f766e}.nova-sticky-head strong::first-letter{color:#f97316}.nova-sticky-status{padding:3px 9px;border-radius:999px;background:#0f9f83;color:#fff;font-size:12px;box-shadow:0 3px 10px rgba(15,159,131,.22)}
    .nova-sticky-body{padding:14px 16px}.nova-sticky-metrics{display:grid;grid-template-columns:repeat(6,minmax(100px,1fr));gap:8px;margin-bottom:12px}
    .nova-sticky-metric{padding:9px 10px;border:1px solid rgba(13,148,136,.16);border-radius:6px;background:rgba(255,255,255,.78)}.nova-sticky-metric span{display:block;color:#64748b;font-size:12px}.nova-sticky-metric b{display:block;margin-top:4px;color:#17333b}.nova-sticky-metric:first-child b,.nova-sticky-metric:first-child b a,.nova-sticky-metric:first-child b span{font-size:14px;font-weight:800}
    .nova-sticky-form{display:grid;grid-template-columns:repeat(5,minmax(110px,1fr)) auto;gap:10px;align-items:end}.nova-sticky-form label{display:flex;flex-direction:column;gap:5px;color:#475569;font-size:12px}.nova-sticky-form input,.nova-sticky-form select{height:34px;box-sizing:border-box;border:1px solid rgba(13,148,136,.35);border-radius:5px;background:rgba(255,255,255,.94);color:#17333b;padding:0 9px;outline:none}.nova-sticky-form input:focus,.nova-sticky-form select:focus{border-color:#0d9488;box-shadow:0 0 0 2px rgba(20,184,166,.13)}.nova-sticky-form button{height:34px;border:0;border-radius:5px;background:#f59e0b;color:#17212b;font-weight:700;padding:0 18px;cursor:pointer;box-shadow:0 4px 12px rgba(245,158,11,.22)}.nova-sticky-start-actions{display:flex;align-items:center;gap:9px;height:34px;white-space:nowrap}.nova-sticky-start-actions .nova-sticky-anonymous-option{display:inline-flex;flex-direction:row;align-items:center;gap:5px;height:34px;margin:0;color:#475569}.nova-sticky-start-actions .nova-sticky-anonymous-option input{width:14px;height:14px;margin:0;padding:0;box-shadow:none}.nova-sticky-start-actions button{white-space:nowrap}
    .nova-sticky-cost{margin:10px 0 0;color:#c2410c}.nova-sticky-note{color:#64748b;font-size:12px;margin-top:8px}.nova-sticky-success{padding:8px 12px;background:rgba(16,185,129,.12);color:#047857;border-bottom:1px solid rgba(16,185,129,.22)}
    .nova-sticky-ranking{margin-top:12px;max-height:290px;overflow:auto;border:1px solid rgba(13,148,136,.14);border-radius:6px;background:rgba(255,255,255,.62)}.nova-sticky-ranking table{width:100%;border-collapse:collapse}.nova-sticky-ranking th,.nova-sticky-ranking td{padding:8px 10px;text-align:center;border-bottom:1px solid rgba(13,148,136,.11)}.nova-sticky-ranking th{position:sticky;top:0;z-index:1;color:#0f766e;background:#ddf7f2}.nova-sticky-ranking tbody tr:nth-child(even){background:rgba(224,242,254,.34)}.nova-sticky-ranking tbody tr:hover{background:rgba(153,246,228,.24)}.nova-sticky-ranking td:nth-child(2),.nova-sticky-ranking td:nth-child(2) a,.nova-sticky-ranking td:nth-child(2) span{font-size:14px;font-weight:800;text-shadow:0 1px 0 #fff}.nova-sticky-anonymous{color:#64748b!important;font-style:italic;font-weight:700!important}.nova-sticky-privacy{display:flex;justify-content:flex-end;margin:8px 0 -2px}.nova-sticky-privacy button{height:28px;padding:0 10px;border:1px solid rgba(13,148,136,.28);border-radius:5px;background:#fff;color:#0f766e;font-size:11px;font-weight:700;cursor:pointer}
    @media(max-width:1200px){.nova-sticky-panel{width:92%;min-width:0}.nova-sticky-metrics{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:700px){.nova-sticky-panel{width:97%}.nova-sticky-metrics{grid-template-columns:repeat(2,1fr)}.nova-sticky-form{grid-template-columns:repeat(2,1fr)}.nova-sticky-form button{grid-column:1/-1}}
    </style>
    <section class="nova-sticky-panel" id="nova-sticky-panel">
      <?php if ($success): ?><div class="nova-sticky-success">助推操作已完成。</div><?php endif; ?>
      <div class="nova-sticky-body">
      <?php if ($campaign): ?>
        <div class="nova-sticky-metrics">
          <div class="nova-sticky-metric"><span>发起人</span><b><?php echo nova_sticky_user_display((int)$campaign['sponsor_id'], ($campaign['sponsor_anonymous'] ?? 'no') === 'yes'); ?></b></div>
          <div class="nova-sticky-metric"><span>促销</span><b><?php echo (int)$campaign['sponsor_id'] === 0 ? '保持原优惠' : ([2 => '免费', 3 => '2X', 4 => '2X 免费'][(int)$campaign['promotion_type']] ?? '-'); ?></b></div>
          <div class="nova-sticky-metric"><span>竞价魔力</span><b><?php echo number_format((float)$campaign['bid_bonus'], 2); ?></b></div>
          <div class="nova-sticky-metric"><span>奖励池</span><b><?php echo number_format((float)$campaign['reward_pool'], 2); ?></b></div>
          <div class="nova-sticky-metric"><span>奖励人数</span><b><?php echo intval($campaign['winner_count']); ?></b></div>
          <div class="nova-sticky-metric"><span>剩余时间</span><b data-nova-countdown="<?php echo htmlspecialchars($campaign['ends_at']); ?>">--</b></div>
        </div>
        <form class="nova-sticky-privacy" method="post" action="nova-sticky-promotion-action.php">
          <input type="hidden" name="action" value="privacy"><input type="hidden" name="torrent_id" value="<?php echo $torrentId; ?>"><input type="hidden" name="token" value="<?php echo nova_sticky_token($torrentId, 'privacy', $userId); ?>"><input type="hidden" name="anonymous" value="<?php echo $viewerAnonymous ? 'no' : 'yes'; ?>">
          <button type="submit"><?php echo $viewerAnonymous ? '显示真实名称' : '匿名显示我的名称'; ?></button>
        </form>
        <?php echo nova_sticky_render_rankings($campaign); ?>
        <?php if ((int)$campaign['sponsor_id'] === $userId): ?>
        <form class="nova-sticky-form" method="post" action="nova-sticky-promotion-action.php" style="margin-top:14px">
          <input type="hidden" name="action" value="append"><input type="hidden" name="torrent_id" value="<?php echo $torrentId; ?>"><input type="hidden" name="token" value="<?php echo nova_sticky_token($torrentId, 'append', $userId); ?>">
          <label>追加小时<input type="number" name="hours" min="0" max="168" value="0"></label><label>追加竞价<input type="number" name="bid_bonus" min="0" step="0.01" value="0"></label><label>追加奖励池<input type="number" name="reward_pool" min="0" step="0.01" value="0"></label><label>增加获奖人数<input type="number" name="winner_count_add" min="0" max="20" value="0"></label><span></span><button type="submit">确认追加</button>
        </form>
        <?php endif; ?>
      <?php else: ?>
        <form class="nova-sticky-form" id="nova-sticky-start" method="post" action="nova-sticky-promotion-action.php">
          <input type="hidden" name="action" value="start"><input type="hidden" name="torrent_id" value="<?php echo $torrentId; ?>"><input type="hidden" name="token" value="<?php echo nova_sticky_token($torrentId, 'start', $userId); ?>">
          <label>促销类型<select name="promotion_type"><option value="4">2X 免费</option><option value="2">免费</option><option value="3">2X 上传</option></select></label><label>助推小时<input type="number" name="hours" min="1" max="168" value="24" required></label><label>竞价魔力<input type="number" name="bid_bonus" min="0" step="0.01" value="0"></label><label>上传奖励池<input type="number" name="reward_pool" min="0" step="0.01" value="1000"></label><label>奖励人数<input type="number" name="winner_count" min="1" max="20" value="3" required></label><div class="nova-sticky-start-actions"><label class="nova-sticky-anonymous-option"><input type="checkbox" name="anonymous" value="yes"><span>匿名发起</span></label><button type="submit">发起助推</button></div>
        </form>
        <div class="nova-sticky-cost">预计总费用：<b id="nova-sticky-cost">--</b> 魔力</div>
        <div class="nova-sticky-note">占位费 = <?php echo number_format($baseRate, 2); ?> × 时长 × 当前资源系数 <?php echo number_format($coefficient, 4); ?>；再加竞价和奖励池。付费助推排序高于系统置顶，同级按竞价从高到低排列。</div>
      <?php endif; ?>
      </div>
    </section>
    <script>
    (function(){
      var form=document.getElementById('nova-sticky-start'), coefficient=<?php echo json_encode($coefficient); ?>, base=<?php echo json_encode($baseRate); ?>;
      function cost(){if(!form)return;var h=parseFloat(form.hours.value)||0,b=parseFloat(form.bid_bonus.value)||0,r=parseFloat(form.reward_pool.value)||0;document.getElementById('nova-sticky-cost').textContent=(coefficient*base*h+b+r).toFixed(2)}
      if(form){form.addEventListener('input',cost);cost()}
      var node=document.querySelector('[data-nova-countdown]');if(node){var end=new Date(node.getAttribute('data-nova-countdown').replace(' ','T')).getTime();var tick=function(){var s=Math.max(0,Math.floor((end-Date.now())/1000)),d=Math.floor(s/86400),h=Math.floor(s%86400/3600),m=Math.floor(s%3600/60);node.textContent=(d?d+'天 ':'')+h+'小时 '+m+'分';};tick();setInterval(tick,30000)}
      var rankingBody=document.getElementById('nova-sticky-ranking-body');
      function size(bytes){var units=['B','KB','MB','GB','TB','PB'],i=0,n=Number(bytes)||0;while(n>=1024&&i<units.length-1){n/=1024;i++}return n.toFixed(i?2:0)+' '+units[i]}
      function refreshRanking(){if(!rankingBody)return;fetch('nova-sticky-promotion-status.php?torrent_id=<?php echo $torrentId; ?>',{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(data){if(!data.active)return;rankingBody.textContent='';if(!data.rankings.length){var empty=document.createElement('tr'),cell=document.createElement('td');cell.colSpan=4;cell.textContent='暂无上传记录';empty.appendChild(cell);rankingBody.appendChild(empty);return}data.rankings.forEach(function(item){var tr=document.createElement('tr');[item.rank,item.display_html,size(item.uploaded_delta),Number(item.estimated_reward).toFixed(2)].forEach(function(value,index){var td=document.createElement('td');if(index===1)td.innerHTML=value;else td.textContent=value;tr.appendChild(td)});rankingBody.appendChild(tr)})}).catch(function(){})}
      if(rankingBody)setInterval(refreshRanking,20000);
    })();
    </script>
    </td></tr>
    <?php
}
