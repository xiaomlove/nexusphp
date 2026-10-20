<?php

function magic_pool_config()
{
    return [
        'required_points' => 2000000,
        'promotion_days' => 3,
        'rank_limit' => 10,
        'history_per_page' => 10,
    ];
}

function magic_pool_ensure_tables()
{
    sql_query("CREATE TABLE IF NOT EXISTS `magic_pool` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `total_points` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT '累计魔力值',
        `last_reset_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_check_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (mysql_num_rows(sql_query("SELECT id FROM magic_pool WHERE id = 1")) === 0) {
        sql_query("INSERT INTO magic_pool (id, total_points) VALUES (1, 0.00)");
    }

    sql_query("CREATE TABLE IF NOT EXISTS `magic_pool_monthly_stats` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `uid` int(11) NOT NULL COMMENT '用户ID',
        `total_donate` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT '当月累计捐赠',
        `year` int(4) NOT NULL COMMENT '年份',
        `month` int(2) NOT NULL COMMENT '月份',
        PRIMARY KEY (`id`),
        UNIQUE KEY `uid_month` (`uid`, `year`, `month`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function magic_pool_update_promotion_time()
{
    $start = date('Y-m-01 00:00:00', strtotime('+1 month'));
    $end = date('Y-m-03 23:59:59', strtotime('+1 month'));

    sql_query("UPDATE torrents_state
        SET global_sp_state = 4, begin = " . sqlesc($start) . ", deadline = " . sqlesc($end) . "
        WHERE id = 1");
    do_log("魔力池达标，促销时间已设置为：{$start} 至 {$end}", 'magic_pool');
}

function magic_pool_check_status($config)
{
    $pool = mysql_fetch_assoc(sql_query("SELECT * FROM magic_pool WHERE id = 1"));
    if (!$pool) {
        return;
    }

    $today = (int) date('d');
    $currentMonth = date('m');
    $lastCheck = strtotime($pool['last_check_time']);
    $forceCheck = (float) $pool['total_points'] >= $config['required_points'];

    if (!$forceCheck && $lastCheck && date('Y-m-d', $lastCheck) === date('Y-m-d')) {
        return;
    }

    $lastReset = strtotime($pool['last_reset_time']);
    if ($today === 1 && (!$lastReset || date('Y-m', $lastReset) !== date('Y-m'))) {
        sql_query("UPDATE magic_pool SET total_points = 0, last_reset_time = NOW(), last_check_time = NOW() WHERE id = 1");
        do_log('魔力池已重置（每月1日）', 'magic_pool');
        return;
    }

    $promotion = mysql_fetch_assoc(sql_query("SELECT global_sp_state, deadline FROM torrents_state WHERE id = 1"));
    $isActive = $promotion
        && (int) $promotion['global_sp_state'] === 4
        && strtotime($promotion['deadline']) >= time();

    if ($forceCheck && !$isActive) {
        magic_pool_update_promotion_time();
    }

    sql_query("UPDATE magic_pool SET last_check_time = NOW() WHERE id = 1");
}

function magic_pool_handle_donation($config, $redirect = 'mybonus.php?pool_success=1#magic-pool')
{
    global $CURUSER;

    $amountInput = trim((string)($_POST['amount'] ?? ''));
    if (!preg_match('/^[1-9][0-9]{0,11}$/', $amountInput)) {
        stderr('无效金额', '请输入大于 0 的整数魔力值。');
    }
    $amount = (int)$amountInput;

    $userId = intval($CURUSER['id']);
    sql_query('START TRANSACTION');

    $user = mysql_fetch_assoc(sql_query("SELECT seedbonus FROM users WHERE id = {$userId} FOR UPDATE"));
    $pool = mysql_fetch_assoc(sql_query("SELECT total_points FROM magic_pool WHERE id = 1 FOR UPDATE"));

    if (!$user || !$pool) {
        sql_query('ROLLBACK');
        stderr('捐赠失败', '暂时无法读取魔力池数据，请稍后重试。');
    }

    $currentBonus = (float) $user['seedbonus'];
    $currentTotal = (float) $pool['total_points'];
    if ($currentBonus < $amount) {
        sql_query('ROLLBACK');
        stderr('魔力不足', '您的魔力值不足，无法完成本次捐赠。');
    }

    $remaining = max(0, $config['required_points'] - $currentTotal);
    if ($remaining <= 0) {
        sql_query('ROLLBACK');
        stderr('魔力池已达标', '本月魔力池已经达标，暂不支持继续捐赠。');
    }
    if ($amount > $remaining) {
        sql_query('ROLLBACK');
        stderr('捐赠额度超出', '当前魔力池仅剩 ' . number_format($remaining) . ' 魔力值可捐赠，请选择较小额度。');
    }

    $newBonus = $currentBonus - $amount;
    sql_query("UPDATE users SET seedbonus = " . sqlesc($newBonus) . " WHERE id = {$userId}");
    sql_query("UPDATE magic_pool SET total_points = total_points + {$amount} WHERE id = 1");

    $comment = "捐赠{$amount}魔力值到全局促销池";
    sql_query("INSERT INTO bonus_logs (
        uid, old_total_value, value, new_total_value, comment, business_type, created_at, updated_at
    ) VALUES (
        {$userId}, " . sqlesc($currentBonus) . ", " . sqlesc(-$amount) . ", " . sqlesc($newBonus) . ",
        " . sqlesc($comment) . ", 'donate_magic', NOW(), NOW()
    )");

    $year = intval(date('Y'));
    $month = intval(date('m'));
    sql_query("INSERT INTO magic_pool_monthly_stats (uid, total_donate, year, month)
        VALUES ({$userId}, {$amount}, {$year}, {$month})
        ON DUPLICATE KEY UPDATE total_donate = total_donate + VALUES(total_donate)");

    sql_query('COMMIT');

    if ($currentTotal + $amount >= $config['required_points']) {
        magic_pool_update_promotion_time();
    }

    nexus_redirect($redirect);
}

function magic_pool_get_data($userId, $config, $page)
{
    $page = max(1, intval($page));
    $offset = ($page - 1) * $config['history_per_page'];
    $userId = intval($userId);
    $year = intval(date('Y'));
    $month = intval(date('m'));

    $pool = mysql_fetch_assoc(sql_query("SELECT total_points FROM magic_pool WHERE id = 1"));
    $promotionRow = mysql_fetch_assoc(sql_query("SELECT global_sp_state, begin, deadline FROM torrents_state WHERE id = 1"));
    $promotion = [
        'is_active' => $promotionRow
            && (int) $promotionRow['global_sp_state'] === 4
            && strtotime($promotionRow['deadline']) >= time(),
        'start_time' => $promotionRow['begin'] ?? '',
        'end_time' => $promotionRow['deadline'] ?? '',
    ];

    $totalRow = mysql_fetch_assoc(sql_query("SELECT COUNT(*) AS total FROM bonus_logs
        WHERE uid = {$userId} AND business_type = 'donate_magic'"));
    $historyTotal = intval($totalRow['total'] ?? 0);
    $history = [];
    $historyResult = sql_query("SELECT created_at, value FROM bonus_logs
        WHERE uid = {$userId} AND business_type = 'donate_magic'
        ORDER BY created_at DESC LIMIT {$offset}, " . intval($config['history_per_page']));
    while ($row = mysql_fetch_assoc($historyResult)) {
        $history[] = $row;
    }

    $ranks = [];
    $rankResult = sql_query("SELECT u.id, u.username, m.total_donate
        FROM magic_pool_monthly_stats m
        LEFT JOIN users u ON u.id = m.uid
        WHERE m.year = {$year} AND m.month = {$month}
        ORDER BY m.total_donate DESC
        LIMIT " . intval($config['rank_limit']));
    while ($row = mysql_fetch_assoc($rankResult)) {
        $ranks[] = $row;
    }

    return [
        'pool' => $pool ?: ['total_points' => 0],
        'promotion' => $promotion,
        'history' => $history,
        'history_total' => $historyTotal,
        'ranks' => $ranks,
        'page' => $page,
    ];
}

function magic_pool_render($user, $config, $page = 1)
{
    $data = magic_pool_get_data($user['id'], $config, $page);
    $total = (float) $data['pool']['total_points'];
    $target = (float) $config['required_points'];
    $remaining = max(0, $target - $total);
    $progress = $target > 0 ? min(100, ($total / $target) * 100) : 0;
    $visualProgress = $progress > 0 ? max(0.8, $progress) : 0;
    $isFull = $total >= $target;
    $totalPages = max(1, (int) ceil($data['history_total'] / $config['history_per_page']));
    $page = min($data['page'], $totalPages);
    ?>
    <style>
        .nova-magic-pool-donate input[type="number"]{box-sizing:border-box;width:150px;height:38px;margin:0;padding:0 12px;border:1px solid #d3e2e5;border-radius:7px;background:#fff;color:#17313b;font:inherit;line-height:38px;outline:0}
        .nova-magic-pool-donate input[type="number"]:focus{border-color:#0f9f8d;box-shadow:0 0 0 3px rgba(15,159,141,.13)}
        .nova-magic-pool-donate input[type="number"]:disabled{background:#f2f6f7;color:#91a1a7;cursor:not-allowed}
        @media(max-width:760px){.nova-magic-pool-donate input[type="number"]{width:100%}}
    </style>
    <section class="nova-magic-pool<?= $isFull ? ' is-complete' : '' ?>" id="magic-pool">
        <?php if (isset($_GET['pool_success']) && $_GET['pool_success'] == '1'): ?>
            <div class="nova-magic-pool-alert is-success"><strong>捐赠成功</strong><span>感谢你为全站促销助力。</span></div>
        <?php endif; ?>

        <?php if ($data['promotion']['is_active']): ?>
            <div class="nova-magic-pool-alert is-promotion">
                <strong>全局双倍免费促销进行中</strong>
                <span><?= htmlspecialchars($data['promotion']['start_time']) ?> 至 <?= htmlspecialchars($data['promotion']['end_time']) ?></span>
            </div>
        <?php endif; ?>

        <div class="nova-magic-pool-hero">
            <div class="nova-magic-pool-heading">
                <div>
                    <span class="nova-magic-pool-kicker">GLOBAL BONUS POOL</span>
                    <h1>全局促销魔力池</h1>
                    <p>当月累计达到 200 万魔力值，下个月 1 至 3 日自动开启全站 2×Free。</p>
                </div>
                <div class="nova-magic-pool-percent">
                    <span>本月已完成</span>
                    <strong><?= number_format($progress, 1) ?><small>%</small></strong>
                </div>
            </div>

            <div class="nova-magic-pool-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= round($progress, 1) ?>">
                <span style="width: <?= $visualProgress ?>%"></span>
            </div>

            <div class="nova-magic-pool-stats">
                <div><span>当前累计</span><strong><?= number_format($total) ?></strong></div>
                <div><span>本月目标</span><strong><?= number_format($target) ?></strong></div>
                <div><span><?= $isFull ? '达标状态' : '距离达标' ?></span><strong><?= $isFull ? '已完成' : number_format($remaining) ?></strong></div>
            </div>
        </div>

        <form class="nova-magic-pool-donate" action="mybonus.php?action=magic_pool_donate#magic-pool" method="post">
            <div class="nova-magic-pool-donate-copy">
                <strong>捐赠魔力，助力全站促销</strong>
                <span>当前可用魔力 <?= number_format((float) $user['seedbonus'], 1) ?></span>
            </div>
            <label>
                <span>捐赠额度</span>
                <input type="number" name="amount" min="1" max="<?= max(0, (int) floor(min((float) $user['seedbonus'], $remaining))) ?>" step="1" inputmode="numeric" placeholder="请输入魔力值" required<?= $isFull ? ' disabled' : '' ?> />
            </label>
            <button type="submit" name="submit"<?= $isFull ? ' disabled' : '' ?>><?= $isFull ? '本月已达标' : '捐赠魔力' ?></button>
        </form>

        <div class="nova-magic-pool-grid">
            <section class="nova-magic-pool-section">
                <div class="nova-magic-pool-section-title"><h2>本月捐赠榜</h2><span>TOP <?= intval($config['rank_limit']) ?></span></div>
                <?php if (!$data['ranks']): ?>
                    <div class="nova-magic-pool-empty">本月暂时还没有捐赠记录</div>
                <?php else: ?>
                    <div class="nova-magic-pool-table-wrap">
                        <table class="nova-magic-pool-table">
                            <thead><tr><th>排名</th><th>用户</th><th>累计捐赠</th></tr></thead>
                            <tbody>
                            <?php foreach ($data['ranks'] as $index => $rank): ?>
                                <tr class="rank-<?= $index + 1 ?>">
                                    <td><span class="nova-magic-pool-rank"><?= $index + 1 ?></span></td>
                                    <td><a href="userdetails.php?id=<?= intval($rank['id']) ?>"><?= htmlspecialchars($rank['username'] ?? '未知用户') ?></a></td>
                                    <td><?= number_format((float) $rank['total_donate']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="nova-magic-pool-section">
                <div class="nova-magic-pool-section-title"><h2>我的捐赠记录</h2><span>共 <?= intval($data['history_total']) ?> 次</span></div>
                <?php if (!$data['history']): ?>
                    <div class="nova-magic-pool-empty">你还没有捐赠记录</div>
                <?php else: ?>
                    <div class="nova-magic-pool-table-wrap">
                        <table class="nova-magic-pool-table">
                            <thead><tr><th>时间</th><th>捐赠额度</th></tr></thead>
                            <tbody>
                            <?php foreach ($data['history'] as $item): ?>
                                <tr><td><?= htmlspecialchars($item['created_at']) ?></td><td><?= number_format(abs((float) $item['value'])) ?> 魔力</td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1): ?>
                        <nav class="nova-magic-pool-pagination" aria-label="捐赠记录分页">
                            <?php if ($page > 1): ?><a href="mybonus.php?pool_page=<?= $page - 1 ?>#magic-pool">上一页</a><?php endif; ?>
                            <span>第 <?= $page ?> / <?= $totalPages ?> 页</span>
                            <?php if ($page < $totalPages): ?><a href="mybonus.php?pool_page=<?= $page + 1 ?>#magic-pool">下一页</a><?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </section>
    <?php
}
