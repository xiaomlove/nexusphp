<?php
require_once("../include/bittorrent.php");
dbconn(true);
loggedinorreturn();
// ini_set('display_errors', 1);
// 检查用户权限
if (get_user_class() < UC_UPLOADER) {
    stderr("访问被拒绝", "只有发布员及以上等级的用户才能访问此页面");
    stdfoot();
    exit;
}
function format_interval_seconds($interval_seconds)
{
    if ($interval_seconds == 0) {
        return '永久';
    }
    $seconds = $interval_seconds;
    $parts = [];
    $days = floor($seconds / 86400);
    $seconds %= 86400;
    $hours = floor($seconds / 3600);
    $seconds %= 3600;
    $minutes = floor($seconds / 60);
    $seconds %= 60;
    if ($days > 0) $parts[] = $days . '天';
    if ($hours > 0) $parts[] = $hours . '小时';
    if ($minutes > 0) $parts[] = $minutes . '分钟';
    if ($seconds > 0) $parts[] = $seconds . '秒';
    return implode('', $parts);
}
// 获取用户考核信息
$uid = (int)$CURUSER['id'];
$assessment_info = null;
$requirement_info = null;

// 查询用户的考核分配
$assessment_sql = "SELECT uaa.*, ar.* 
                 FROM user_assessment_assignments uaa 
                 LEFT JOIN assessment_requirements ar ON uaa.requirement_id = ar.id 
                 WHERE uaa.uid = " . sqlesc($uid) . " LIMIT 1";
$assessment_res = sql_query($assessment_sql);
if ($assessment_row = mysql_fetch_assoc($assessment_res)) {
    $assessment_info = $assessment_row;
    $requirement_info = $assessment_row;
}

// 获取用户种子相关数据
function get_user_torrent_stats($uid, $interval_seconds)
{
    $pub = ['count' => 0, 'total' => 0];
    $seed = ['count' => 0, 'total' => 0];
    $alloperation = [];
    $approveoperation = [];

    // 针对发布员/转种员的数据
    // 过去发布种子数和体积（总计）
    $r = sql_query("SELECT COUNT(*) AS count, SUM(size) AS total FROM torrents WHERE owner = " . intval($uid));
    $pub_row = mysql_fetch_assoc($r);
    $pub['count'] = (int)($pub_row['count'] ?? 0);
    $pub['total'] = (float)($pub_row['total'] ?? 0);

    // 过去interval_seconds时间发布种子数和体积：用来比较min_publish_quantity_req和min_publish_volume_req
    $r_past = sql_query("SELECT COUNT(*) AS cnt, SUM(size) AS total FROM torrents WHERE owner = " . intval($uid) . " AND added >= NOW() - INTERVAL " . intval($interval_seconds) . " SECOND");
    $pub_past_row = mysql_fetch_assoc($r_past);
    $pub['count_past'] = (int)($pub_past_row['cnt'] ?? 0);
    $pub['total_past'] = (float)($pub_past_row['total'] ?? 0);

    // 针对保种员的数据
    // 做种总数和总体积
    $seeding = [];
    $res = sql_query("SELECT peers.torrent, torrents.size, snatched.seedtime, peers.started
        FROM peers
        LEFT JOIN torrents ON peers.torrent = torrents.id
        LEFT JOIN snatched ON torrents.id = snatched.torrentid AND snatched.userid = " . intval($uid) . "
        WHERE peers.userid=" . intval($uid) . " AND peers.seeder='yes'");
    $now_ts = time();
    $past_ts = $now_ts - $interval_seconds;
    while ($row = mysql_fetch_assoc($res)) {
        $seeding[$row['torrent']] = [
            'size' => (int)$row['size'],
            'seedtime' => (int)$row['seedtime'],
            'started' => isset($row['started']) ? strtotime($row['started']) : null,
        ];
    }
    $seed['count'] = count($seeding);
    $seed['total'] = array_sum(array_column($seeding, 'size'));

    // 近interval_seconds时间内开始做种的数量和体积：用来比较min_seed_quantity_req和min_seed_volume_req
    $count_past = 0;
    $total_past = 0;
    foreach ($seeding as $s) {
        if (!empty($s['started']) && $s['started'] >= $past_ts) {
            $count_past++;
            $total_past += $s['size'];
        }
    }
    $seed['count_past'] = $count_past;
    $seed['total_past'] = $total_past;

    // 针对审核员的数据
    // 历史操作总数
    $r_all = sql_query("SELECT COUNT(*) AS cnt FROM torrent_operation_logs WHERE uid = " . intval($uid) . " AND (action_type = 'approval_allow' OR action_type = 'approval_deny' OR action_type = 'edit')");
    $row_all = mysql_fetch_assoc($r_all);
    $alloperation['count'] = isset($row_all['cnt']) ? (int)$row_all['cnt'] : 0;

    // 近interval_seconds天操作总数：用来比较min_audit_total_req
    $r_all_past = sql_query("SELECT COUNT(*) AS cnt FROM torrent_operation_logs WHERE uid = " . intval($uid) . " AND (action_type = 'approval_allow' OR action_type = 'approval_deny' OR action_type = 'edit') AND updated_at >= NOW() - INTERVAL " . intval($interval_seconds) . " SECOND");
    $row_all_past = mysql_fetch_assoc($r_all_past);
    $alloperation['count_past'] = isset($row_all_past['cnt']) ? (int)$row_all_past['cnt'] : 0;

    // 历史通过审核总数
    $r_approve = sql_query("SELECT COUNT(*) AS cnt FROM torrent_operation_logs WHERE uid = " . intval($uid) . " AND action_type = 'approval_allow'");
    $row_approve = mysql_fetch_assoc($r_approve);
    $approveoperation['count'] = isset($row_approve['cnt']) ? (int)$row_approve['cnt'] : 0;

    // 近interval_seconds天通过审核总数：用来比较min_audit_pass_req
    $r_approve_past = sql_query("SELECT COUNT(*) AS cnt FROM torrent_operation_logs WHERE uid = " . intval($uid) . " AND action_type = 'approval_allow' AND updated_at >= NOW() - INTERVAL " . intval($interval_seconds) . " SECOND");
    $row_approve_past = mysql_fetch_assoc($r_approve_past);
    $approveoperation['count_past'] = isset($row_approve_past['cnt']) ? (int)$row_approve_past['cnt'] : 0;

    return [
        'pub' => $pub,
        'seed' => $seed,
        'alloperation' => $alloperation,
        'approveoperation' => $approveoperation,
    ];
}

// 处理领取工资请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_salary') {
    $response = array();

    // Salary is paid by the scheduled task. Keep the old calculation below
    // unreachable so a crafted request cannot trigger a duplicate manual payout.
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => '主动领取已关闭，工资将在考核周期结束后由系统自动发放。',
    ], JSON_UNESCAPED_UNICODE);
    exit;

    if (!$assessment_info) {
        $response['success'] = false;
        $response['message'] = "您没有分配考核，无法领取工资";
        echo json_encode($response);
        exit;
    }

    $interval_seconds = (int)$requirement_info['interval_seconds'];

    // 检查是否在允许的时间间隔内
    $last_claim_sql = "SELECT created_at FROM uploader_salary_log WHERE user_id = " . sqlesc($uid) . " ORDER BY created_at DESC LIMIT 1";
    $last_claim_res = sql_query($last_claim_sql);

    if ($last_claim_row = mysql_fetch_assoc($last_claim_res)) {
        $last_claim_time = strtotime($last_claim_row['created_at']);
        $current_time = time();
        $time_diff = $current_time - $last_claim_time;

        if ($time_diff < $interval_seconds) {
            $remaining_time = $interval_seconds - $time_diff;
            $remaining_days = floor($remaining_time / 86400);
            $remaining_hours = floor(($remaining_time % 86400) / 3600);
            $remaining_minutes = floor(($remaining_time % 3600) / 60);
            $remaining_seconds = $remaining_time % 60;

            $response['success'] = false;
            $response['message'] = "距离上次领取时间不足，还需等待 {$remaining_days}天 {$remaining_hours}小时 {$remaining_minutes}分钟 {$remaining_seconds}秒";
            echo json_encode($response);
            exit;
        }
    }

    // 检查考核要求是否达到
    $stats = get_user_torrent_stats($uid, $interval_seconds);
    $can_claim = false;
    $base_salary = 0;
    $bonus_salary = 0;
    $total_salary = 0;

    switch ($requirement_info['assessment_type']) {
        case 1: // 发布员/转种员
            if (
                $stats['pub']['count_past'] >= $requirement_info['min_publish_quantity_req'] &&
                $stats['pub']['total_past'] >= $requirement_info['min_publish_volume_req'] * 1024 * 1024 * 1024
            ) {
                $can_claim = true;
                $base_salary = $requirement_info['base_salary'];

                // 计算额外奖励
                if (
                    $stats['pub']['count_past'] > $requirement_info['publish_quantity_req'] &&
                    $stats['pub']['total_past'] > $requirement_info['publish_volume_req'] * 1024 * 1024 * 1024
                ) {
                    $excess_count = $stats['pub']['count_past'] - $requirement_info['publish_quantity_req'];
                    $excess_volume = ($stats['pub']['total_past'] / (1024 * 1024 * 1024)) - $requirement_info['publish_volume_req'];

                    $bonus_units = min(
                        floor($excess_count / $requirement_info['publish_bonus_qty_ratio']),
                        floor($excess_volume / $requirement_info['publish_bonus_vol_ratio'])
                    );

                    $bonus_salary = $bonus_units * $requirement_info['publish_bonus_amount'];
                    $bonus_salary = min($bonus_salary, $requirement_info['publish_max_bonus']);
                }
            }
            break;

        case 2: // 保种员
            if (
                $stats['seed']['count_past'] >= $requirement_info['min_seed_quantity_req'] &&
                $stats['seed']['total_past'] >= $requirement_info['min_seed_volume_req'] * 1024 * 1024 * 1024
            ) {
                $can_claim = true;
                $base_salary = $requirement_info['base_salary'];

                // 计算额外奖励
                if (
                    $stats['seed']['count_past'] > $requirement_info['seed_quantity_req'] &&
                    $stats['seed']['total_past'] > $requirement_info['seed_volume_req'] * 1024 * 1024 * 1024
                ) {
                    $excess_count = $stats['seed']['count_past'] - $requirement_info['seed_quantity_req'];
                    $excess_volume = ($stats['seed']['total_past'] / (1024 * 1024 * 1024)) - $requirement_info['seed_volume_req'];

                    $bonus_units = min(
                        floor($excess_count / $requirement_info['seed_bonus_qty_ratio']),
                        floor($excess_volume / $requirement_info['seed_bonus_vol_ratio'])
                    );

                    $bonus_salary = $bonus_units * $requirement_info['seed_bonus_amount'];
                    $bonus_salary = min($bonus_salary, $requirement_info['seed_max_bonus']);
                }
            }
            break;

        case 3: // 审核员
            if (
                $stats['alloperation']['count_past'] >= $requirement_info['min_audit_total_req'] &&
                $stats['approveoperation']['count_past'] >= $requirement_info['min_audit_pass_req']
            ) {
                $can_claim = true;
                $base_salary = $requirement_info['base_salary'];

                // 计算额外奖励
                if (
                    $stats['alloperation']['count_past'] > $requirement_info['audit_total_req'] &&
                    $stats['approveoperation']['count_past'] > $requirement_info['audit_pass_req']
                ) {
                    $excess_total = $stats['alloperation']['count_past'] - $requirement_info['audit_total_req'];
                    $excess_pass = $stats['approveoperation']['count_past'] - $requirement_info['audit_pass_req'];

                    $bonus_units = min(
                        floor($excess_total / $requirement_info['audit_bonus_total_ratio']),
                        floor($excess_pass / $requirement_info['audit_bonus_pass_ratio'])
                    );

                    $bonus_salary = $bonus_units * $requirement_info['audit_bonus_amount'];
                    $bonus_salary = min($bonus_salary, $requirement_info['audit_max_bonus']);
                }
            }
            break;

        case 4: // 其他
            $can_claim = true;
            $base_salary = $requirement_info['base_salary'];
            break;
    }

    if (!$can_claim) {
        $response['success'] = false;
        $response['message'] = "您未达到考核要求，无法领取工资";
        echo json_encode($response);
        exit;
    }

    $total_salary = $base_salary + $bonus_salary;

    // 更新用户魔力值
    $bonus_before = (int)$CURUSER['seedbonus'];
    $bonus_after = $bonus_before + $total_salary;

    sql_query("UPDATE users SET seedbonus = " . sqlesc($bonus_after) . " WHERE id = " . sqlesc($uid));

    // 记录领取日志
    sql_query("INSERT INTO uploader_salary_log (user_id, username, salary_amount, bonus_before, bonus_after) VALUES (" . sqlesc($uid) . ", " . sqlesc($CURUSER['username']) . ", " . sqlesc($total_salary) . ", " . sqlesc($bonus_before) . ", " . sqlesc($bonus_after) . ")");

    // 更新当前用户的魔力值
    $CURUSER['seedbonus'] = $bonus_after;
    $response['success'] = true;
    $response['message'] = "恭喜！成功领取 {$total_salary} 魔力值工资！";
    $response['bonus_after'] = $bonus_after;
    $response['base_salary'] = $base_salary;
    $response['bonus_salary'] = $bonus_salary;
    echo json_encode($response);
    exit;
}

// 检查是否可以领取工资
$can_claim = false;
$remaining_time_text = "";
$salary_info = null;

if ($assessment_info) {
    $interval_seconds = (int)$requirement_info['interval_seconds'];
    $stats = get_user_torrent_stats($uid, $interval_seconds);

    // 检查时间间隔
    $last_claim_sql = "SELECT created_at FROM uploader_salary_log WHERE user_id = " . sqlesc($uid) . " ORDER BY created_at DESC LIMIT 1";
    $last_claim_res = sql_query($last_claim_sql);

    $time_ok = true;
    if ($last_claim_row = mysql_fetch_assoc($last_claim_res)) {
        $last_claim_time = strtotime($last_claim_row['created_at']);
        $current_time = time();
        $time_diff = $current_time - $last_claim_time;

        if ($time_diff < $interval_seconds) {
            $time_ok = false;
            $remaining_time = $interval_seconds - $time_diff;
            $remaining_days = floor($remaining_time / 86400);
            $remaining_hours = floor(($remaining_time % 86400) / 3600);
            $remaining_minutes = floor(($remaining_time % 3600) / 60);
            $remaining_seconds = $remaining_time % 60;
            $remaining_time_text = "还需等待 {$remaining_days}天 {$remaining_hours}小时 {$remaining_minutes}分钟 {$remaining_seconds}秒";
        }
    }

    // 检查考核要求
    $requirement_ok = false;
    $base_salary = 0;
    $bonus_salary = 0;
    $total_salary = 0;

    switch ($requirement_info['assessment_type']) {
        case 1: // 发布员/转种员
            if (
                $stats['pub']['count_past'] >= $requirement_info['min_publish_quantity_req'] &&
                $stats['pub']['total_past'] >= $requirement_info['min_publish_volume_req'] * 1024 * 1024 * 1024
            ) {
                $requirement_ok = true;
                $base_salary = $requirement_info['base_salary'];

                // 计算额外奖励
                if (
                    $stats['pub']['count_past'] > $requirement_info['publish_quantity_req'] &&
                    $stats['pub']['total_past'] > $requirement_info['publish_volume_req'] * 1024 * 1024 * 1024
                ) {
                    $excess_count = $stats['pub']['count_past'] - $requirement_info['publish_quantity_req'];
                    $excess_volume = ($stats['pub']['total_past'] / (1024 * 1024 * 1024)) - $requirement_info['publish_volume_req'];

                    $bonus_units = min(
                        floor($excess_count / $requirement_info['publish_bonus_qty_ratio']),
                        floor($excess_volume / $requirement_info['publish_bonus_vol_ratio'])
                    );

                    $bonus_salary = $bonus_units * $requirement_info['publish_bonus_amount'];
                    $bonus_salary = min($bonus_salary, $requirement_info['publish_max_bonus']);
                }
            }
            break;

        case 2: // 保种员
            if (
                $stats['seed']['count_past'] >= $requirement_info['min_seed_quantity_req'] &&
                $stats['seed']['total_past'] >= $requirement_info['min_seed_volume_req'] * 1024 * 1024 * 1024
            ) {
                $requirement_ok = true;
                $base_salary = $requirement_info['base_salary'];

                // 计算额外奖励
                if (
                    $stats['seed']['count_past'] > $requirement_info['seed_quantity_req'] &&
                    $stats['seed']['total_past'] > $requirement_info['seed_volume_req'] * 1024 * 1024 * 1024
                ) {
                    $excess_count = $stats['seed']['count_past'] - $requirement_info['seed_quantity_req'];
                    $excess_volume = ($stats['seed']['total_past'] / (1024 * 1024 * 1024)) - $requirement_info['seed_volume_req'];

                    $bonus_units = min(
                        floor($excess_count / $requirement_info['seed_bonus_qty_ratio']),
                        floor($excess_volume / $requirement_info['seed_bonus_vol_ratio'])
                    );

                    $bonus_salary = $bonus_units * $requirement_info['seed_bonus_amount'];
                    $bonus_salary = min($bonus_salary, $requirement_info['seed_max_bonus']);
                }
            }
            break;

        case 3: // 审核员
            if (
                $stats['alloperation']['count_past'] >= $requirement_info['min_audit_total_req'] &&
                $stats['approveoperation']['count_past'] >= $requirement_info['min_audit_pass_req']
            ) {
                $requirement_ok = true;
                $base_salary = $requirement_info['base_salary'];

                // 计算额外奖励
                if (
                    $stats['alloperation']['count_past'] > $requirement_info['audit_total_req'] &&
                    $stats['approveoperation']['count_past'] > $requirement_info['audit_pass_req']
                ) {
                    $excess_total = $stats['alloperation']['count_past'] - $requirement_info['audit_total_req'];
                    $excess_pass = $stats['approveoperation']['count_past'] - $requirement_info['audit_pass_req'];

                    $bonus_units = min(
                        floor($excess_total / $requirement_info['audit_bonus_total_ratio']),
                        floor($excess_pass / $requirement_info['audit_bonus_pass_ratio'])
                    );

                    $bonus_salary = $bonus_units * $requirement_info['audit_bonus_amount'];
                    $bonus_salary = min($bonus_salary, $requirement_info['audit_max_bonus']);
                }
            }
            break;

        case 4: // 其他
            $requirement_ok = true;
            $base_salary = $requirement_info['base_salary'];
            break;
    }

    $can_claim = $time_ok && $requirement_ok;
    $total_salary = $base_salary + $bonus_salary;

    $salary_info = [
        'base_salary' => $base_salary,
        'bonus_salary' => $bonus_salary,
        'total_salary' => $total_salary,
        'stats' => $stats,
        'requirement' => $requirement_info
    ];
}

// 获取用户领取历史记录
$history_sql = "SELECT * FROM uploader_salary_log WHERE user_id = " . $CURUSER['id'] . " ORDER BY created_at DESC LIMIT 50";
$history_res = sql_query($history_sql);
$history = array();
while ($row = mysql_fetch_assoc($history_res)) {
    $history[] = $row;
}

// 管理员视图：查看所有有考核的用户概况
$admin_assessments = [];
if (get_user_class() >= UC_SYSOP) {
    $admin_limit = isset($_GET['admin_limit']) ? max(1, min((int)$_GET['admin_limit'], 500)) : 200;
    $aa_sql = "SELECT uaa.uid, u.username, ar.*, uaa.id AS assignment_id
               FROM user_assessment_assignments uaa
               LEFT JOIN users u ON uaa.uid = u.id
               LEFT JOIN assessment_requirements ar ON uaa.requirement_id = ar.id
               ORDER BY uaa.id DESC
               LIMIT " . (int)$admin_limit;
    $aa_res = sql_query($aa_sql);
    while ($row = mysql_fetch_assoc($aa_res)) {
        $row['uid'] = (int)$row['uid'];
        $interval_seconds_tmp = (int)$row['interval_seconds'];
        // 统计数据
        $stats_tmp = get_user_torrent_stats($row['uid'], $interval_seconds_tmp);

        // 时间间隔检查
        $last_claim_row = null;
        $lcr = sql_query("SELECT * FROM uploader_salary_log WHERE user_id=" . sqlesc($row['uid']) . " ORDER BY created_at DESC LIMIT 1");
        if ($lcr && $lcr_tmp = mysql_fetch_assoc($lcr)) {
            $last_claim_row = $lcr_tmp;
        }
        $time_ok_tmp = true; $remaining_secs_tmp = 0; $next_claim_at_text = '';
        if ($last_claim_row) {
            $lt = strtotime($last_claim_row['created_at']);
            $diff = time() - $lt;
            if ($diff < $interval_seconds_tmp) {
                $time_ok_tmp = false;
                $remaining_secs_tmp = $interval_seconds_tmp - $diff;
                $next_claim_at_text = date('Y-m-d H:i:s', time() + $remaining_secs_tmp);
            }
        }

        // 要求检查与可领取金额
        $req_ok_tmp = false; $base_salary_tmp = 0; $bonus_salary_tmp = 0; $total_salary_tmp = 0;
        switch ((int)$row['assessment_type']) {
            case 1: // 发布员/转种员
                if ($stats_tmp['pub']['count_past'] >= $row['min_publish_quantity_req'] &&
                    $stats_tmp['pub']['total_past'] >= $row['min_publish_volume_req'] * 1024 * 1024 * 1024) {
                    $req_ok_tmp = true;
                    $base_salary_tmp = (int)$row['base_salary'];
                    if ($stats_tmp['pub']['count_past'] > $row['publish_quantity_req'] &&
                        $stats_tmp['pub']['total_past'] > $row['publish_volume_req'] * 1024 * 1024 * 1024) {
                        $excess_count = $stats_tmp['pub']['count_past'] - $row['publish_quantity_req'];
                        $excess_vol_gb = ($stats_tmp['pub']['total_past'] / (1024 * 1024 * 1024)) - $row['publish_volume_req'];
                        $bonus_units = min(
                            floor($excess_count / max(1, (int)$row['publish_bonus_qty_ratio'])),
                            floor($excess_vol_gb / max(1, (float)$row['publish_bonus_vol_ratio']))
                        );
                        $bonus_salary_tmp = max(0, $bonus_units) * (int)$row['publish_bonus_amount'];
                        $bonus_salary_tmp = min($bonus_salary_tmp, (int)$row['publish_max_bonus']);
                    }
                }
                break;
            case 2: // 保种员
                if ($stats_tmp['seed']['count_past'] >= $row['min_seed_quantity_req'] &&
                    $stats_tmp['seed']['total_past'] >= $row['min_seed_volume_req'] * 1024 * 1024 * 1024) {
                    $req_ok_tmp = true;
                    $base_salary_tmp = (int)$row['base_salary'];
                    if ($stats_tmp['seed']['count_past'] > $row['seed_quantity_req'] &&
                        $stats_tmp['seed']['total_past'] > $row['seed_volume_req'] * 1024 * 1024 * 1024) {
                        $excess_count = $stats_tmp['seed']['count_past'] - $row['seed_quantity_req'];
                        $excess_vol_gb = ($stats_tmp['seed']['total_past'] / (1024 * 1024 * 1024)) - $row['seed_volume_req'];
                        $bonus_units = min(
                            floor($excess_count / max(1, (int)$row['seed_bonus_qty_ratio'])),
                            floor($excess_vol_gb / max(1, (float)$row['seed_bonus_vol_ratio']))
                        );
                        $bonus_salary_tmp = max(0, $bonus_units) * (int)$row['seed_bonus_amount'];
                        $bonus_salary_tmp = min($bonus_salary_tmp, (int)$row['seed_max_bonus']);
                    }
                }
                break;
            case 3: // 审核员
                if ($stats_tmp['alloperation']['count_past'] >= $row['min_audit_total_req'] &&
                    $stats_tmp['approveoperation']['count_past'] >= $row['min_audit_pass_req']) {
                    $req_ok_tmp = true;
                    $base_salary_tmp = (int)$row['base_salary'];
                    if ($stats_tmp['alloperation']['count_past'] > $row['audit_total_req'] &&
                        $stats_tmp['approveoperation']['count_past'] > $row['audit_pass_req']) {
                        $excess_total = $stats_tmp['alloperation']['count_past'] - $row['audit_total_req'];
                        $excess_pass = $stats_tmp['approveoperation']['count_past'] - $row['audit_pass_req'];
                        $bonus_units = min(
                            floor($excess_total / max(1, (int)$row['audit_bonus_total_ratio'])),
                            floor($excess_pass / max(1, (int)$row['audit_bonus_pass_ratio']))
                        );
                        $bonus_salary_tmp = max(0, $bonus_units) * (int)$row['audit_bonus_amount'];
                        $bonus_salary_tmp = min($bonus_salary_tmp, (int)$row['audit_max_bonus']);
                    }
                }
                break;
            case 4: // 其他
            default:
                $req_ok_tmp = true;
                $base_salary_tmp = (int)$row['base_salary'];
                break;
        }
        $total_salary_tmp = $base_salary_tmp + $bonus_salary_tmp;

        $admin_assessments[] = [
            'uid' => $row['uid'],
            'username' => $row['username'],
            'assessment_name' => $row['assessment_name'],
            'config_name' => $row['config_name'],
            'assessment_type' => (int)$row['assessment_type'],
            'interval_seconds' => $interval_seconds_tmp,
            'base_salary' => $base_salary_tmp,
            'bonus_salary' => $bonus_salary_tmp,
            'total_salary' => $total_salary_tmp,
            'time_ok' => $time_ok_tmp,
            'requirement_ok' => $req_ok_tmp,
            'can_claim' => ($time_ok_tmp && $req_ok_tmp),
            'last_claim' => $last_claim_row ? $last_claim_row['created_at'] : null,
            'last_amount' => $last_claim_row ? (int)$last_claim_row['salary_amount'] : null,
            'next_claim_at' => $next_claim_at_text,
            'stats' => $stats_tmp,
        ];
    }
}

stdhead("考核工资领取");
?>
<style>
    .salary-container {
        text-align: center;
        padding: 20px;
        max-width: 1000px;
        margin: 0 auto;
    }

    .header-section {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 30px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        flex-wrap: wrap;
    }

    .salary-icon {
        width: 80px;
        height: 80px;
        margin-right: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(249, 249, 249, 0.2);
        transition: transform 0.3s ease;
    }

    .salary-icon:hover {
        transform: scale(1.1) rotate(5deg);
    }

    .header-text {
        text-align: left;
    }

    .header-text h1 {
        color: white;
        margin: 0 0 10px 0;
        font-size: 28px;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .header-text p {
        color: rgba(255, 255, 255, 0.9);
        margin: 0;
        font-size: 14px;
        line-height: 1.4;
    }

    .user-info {
        background:rgb(254, 255, 255);
        padding: 15px;
        border-radius: 10px;
        border: 1px solid rgb(242, 242, 242);
        margin-bottom: 20px;
        font-size: 16px;
        text-align: left;
    }

    .assessment-info {
        background:rgb(246, 252, 247);
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        border: 1px solid #c3e6cb;
        text-align: left;
    }

    .no-assessment {
        background:rgb(251, 233, 234);
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        border: 1px solid #f5c6cb;
        text-align: left;
    }

    .salary-section {
        margin-bottom: 30px;
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
    }

    .salary-btn {
        padding: 15px 40px;
        font-size: 18px;
        border-radius: 8px;
        border: none;
        background: #28a745;
        color: #fff;
        cursor: pointer;
        transition: all 0.3s;
        margin: 10px;
    }

    .salary-btn:disabled {
        background: #6c757d;
        cursor: not-allowed;
    }

    .salary-btn:hover:not(:disabled) {
        background: #218838;
        transform: translateY(-2px);
    }

    .claim-status {
        font-size: 18px;
        margin: 15px 0;
        padding: 10px;
        border-radius: 5px;
    }

    .claim-status.can-claim {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .claim-status.cannot-claim {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .stats-section {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin: 20px 0;
        border: 1px solid #ddd;
        text-align: left;
    }

    .stats-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    .stats-table th,
    .stats-table td {
        padding: 12px;
        text-align: center;
        border-bottom: 1px solid #ddd;
    }

    .stats-table th {
        background: #f8f9fa;
        font-weight: bold;
    }

    .stats-table th.period-column,
    .stats-table td.period-column {
        background: #e3f2fd !important;
        font-weight: bold;
        color: #1976d2;
    }

    .stats-table th.min-requirement-column,
    .stats-table td.min-requirement-column {
        background: #fff3e0 !important;
        font-weight: bold;
        color: #f57c00;
    }

    .bonus-calculation {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin: 15px 0;
        font-family: 'Courier New', monospace;
        font-size: 14px;
    }

    .bonus-calculation h4 {
        margin-top: 0;
        color: #495057;
        border-bottom: 2px solid #007bff;
        padding-bottom: 8px;
    }

    .calculation-step {
        margin: 8px 0;
        padding: 5px 0;
    }

    .calculation-result {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        border-radius: 4px;
        padding: 10px;
        margin: 10px 0;
        font-weight: bold;
        color: #155724;
    }

    .salary-breakdown {
        background: #fff3cd;
        padding: 15px;
        border-radius: 10px;
        margin: 15px 0;
        border: 1px solid #ffeaa7;
        text-align: left;
    }

    .history-section {
        background: white;
        border: 1px solid #ddd;
        border-radius: 10px;
        padding: 20px;
        margin-top: 30px;
    }

    .history-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    .history-table th,
    .history-table td {
        padding: 12px;
        text-align: center;
        border-bottom: 1px solid #ddd;
    }

    .history-table th {
        background: #f8f9fa;
        font-weight: bold;
    }

    .history-table tr:hover {
        background: #f8f9fa;
    }

    .salary-amount {
        font-size: 24px;
        color: #28a745;
        font-weight: bold;
        margin: 20px 0;
    }

    /* NovaHD salary workspace */
    .salary-container {
        width: 94%;
        max-width: 1455px;
        padding: 0 0 30px;
        color: #18343d;
        text-align: left;
        box-sizing: border-box;
    }

    .salary-container .header-section {
        position: relative;
        justify-content: space-between;
        min-height: 104px;
        margin: 16px 0 14px;
        padding: 24px 28px;
        border: 1px solid rgba(255, 255, 255, .55);
        border-radius: 8px;
        background: linear-gradient(112deg, #102f3a 0%, #11555a 58%, #0f8276 100%);
        box-shadow: 0 12px 28px rgba(28, 73, 81, .15);
        box-sizing: border-box;
    }

    .salary-container .header-text {
        text-align: left;
    }

    .salary-container .header-text h1 {
        margin: 0 0 7px;
        color: #fff !important;
        font-size: 26px;
        line-height: 1.3;
        text-shadow: none;
    }

    .salary-container .header-text h1::after {
        display: none !important;
        content: none !important;
    }

    .salary-container .header-text p {
        color: rgba(255, 255, 255, .78);
        font-size: 13px;
    }

    .salary-admin-actions {
        display: flex;
        justify-content: flex-end;
        margin: 0 0 14px;
    }

    .salary-admin-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 38px;
        padding: 0 17px;
        border: 1px solid #bddbd9;
        border-radius: 7px;
        background: #fff;
        color: #0b746a !important;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
        box-shadow: 0 5px 14px rgba(35, 80, 87, .08);
    }

    .salary-admin-link:hover {
        border-color: #0f9f8d;
        background: #edf9f7;
    }

    .salary-overview {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        margin: 0 0 14px;
        border: 1px solid #d5e5e7;
        border-radius: 8px;
        background: rgba(255, 255, 255, .9);
        box-shadow: 0 8px 22px rgba(36, 74, 83, .08);
        overflow: hidden;
    }

    .salary-overview-item {
        min-width: 0;
        padding: 15px 18px;
        border-right: 1px solid #e2ecee;
    }

    .salary-overview-item:last-child {
        border-right: 0;
    }

    .salary-overview-item span {
        display: block;
        margin-bottom: 4px;
        color: #748b92;
        font-size: 11px;
        font-weight: 700;
    }

    .salary-overview-item strong {
        display: block;
        overflow: hidden;
        color: #173943;
        font-size: 17px;
        line-height: 1.35;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .salary-overview-item.is-total strong {
        color: #0b8879;
        font-size: 20px;
    }

    .salary-overview-item.is-status strong {
        color: #256f81;
        font-size: 14px;
    }

    .salary-container .user-info,
    .salary-container .assessment-info,
    .salary-container .no-assessment,
    .salary-container .stats-section,
    .salary-container .salary-section,
    .salary-container .history-section,
    .salary-container .salary-breakdown {
        margin: 0 0 14px;
        padding: 18px 20px;
        border: 1px solid #d8e6e8;
        border-radius: 8px;
        background: rgba(255, 255, 255, .9);
        box-shadow: 0 6px 18px rgba(39, 73, 81, .07);
        box-sizing: border-box;
    }

    .salary-container .user-info {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px 18px;
        font-size: 13px;
    }

    .salary-container .user-info h3,
    .salary-container .assessment-info h3 {
        grid-column: 1 / -1;
    }

    .salary-container .user-info p,
    .salary-container .assessment-info p {
        margin: 0;
        line-height: 1.65;
    }

    .salary-container .assessment-info {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        border-color: #cce2df;
        background: #f7fcfb;
    }

    .salary-container .assessment-info p {
        padding: 10px 12px;
        border: 1px solid #deeceb;
        border-radius: 6px;
        background: #fff;
    }

    .salary-container h3,
    .salary-container h4 {
        margin: 0 0 13px;
        color: #193c45 !important;
        font-size: 16px;
        line-height: 1.4;
    }

    .salary-container h3::after,
    .salary-container h4::after {
        display: none !important;
        content: none !important;
    }

    .salary-container .stats-section,
    .salary-container .history-section {
        overflow-x: auto;
    }

    .salary-container .stats-table,
    .salary-container .history-table {
        min-width: 760px;
        margin: 0;
        border: 0;
        border-collapse: separate;
        border-spacing: 0;
        background: transparent;
    }

    .salary-container .stats-table th,
    .salary-container .history-table th {
        padding: 11px 10px;
        border: 0;
        border-bottom: 1px solid #cfdee1;
        background: #eaf5f5 !important;
        color: #34535c;
        font-size: 11px;
        white-space: nowrap;
    }

    .salary-container .stats-table td,
    .salary-container .history-table td {
        padding: 11px 10px;
        border: 0;
        border-bottom: 1px solid #e7eff0;
        background: #fff;
        color: #38535b;
        font-size: 12px;
        white-space: nowrap;
    }

    .salary-container .stats-table tr:last-child td,
    .salary-container .history-table tr:last-child td {
        border-bottom: 0;
    }

    .salary-container .stats-table tr:hover td,
    .salary-container .history-table tr:hover td {
        background: #f1faf8 !important;
    }

    .salary-container .stats-table .period-column {
        background: #eef7ff !important;
        color: #256f9b !important;
    }

    .salary-container .stats-table .min-requirement-column {
        background: #fff8e8 !important;
        color: #a26612 !important;
    }

    .salary-container .bonus-calculation {
        margin: 14px 0 0;
        padding: 16px 18px;
        border: 1px solid #dbe7e9;
        border-radius: 7px;
        background: #f8fbfb;
        color: #405b63;
        font-family: inherit;
        font-size: 12px;
        line-height: 1.65;
    }

    .salary-container .bonus-calculation h4 {
        padding-bottom: 9px;
        border-bottom: 1px solid #d8e6e8;
    }

    .salary-container .calculation-result {
        border-color: #bfe2d6;
        border-radius: 6px;
        background: #edf9f4;
        color: #176b55;
    }

    .salary-container .salary-breakdown {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px 18px;
        border-color: #ead9a9;
        background: #fffbef;
    }

    .salary-container .salary-breakdown h3 {
        grid-column: 1 / -1;
    }

    .salary-container .salary-breakdown p {
        margin: 0;
    }

    .salary-container .salary-section {
        text-align: center;
        background: #f7fbfb;
    }

    .salary-container .salary-amount {
        margin: 8px 0 12px;
        color: #0b8879;
        font-size: 26px;
    }

    .salary-container .claim-status {
        display: inline-block;
        margin: 0 0 12px;
        padding: 8px 14px;
        border-radius: 6px;
        font-size: 13px;
    }

    .salary-container .salary-btn {
        min-height: 40px;
        padding: 0 20px;
        border-radius: 7px !important;
        font-size: 13px;
        line-height: 40px;
        text-align: center;
    }

    .salary-container .salary-btn:disabled {
        background: #7d9197 !important;
        color: #fff !important;
        opacity: 1;
    }

    @media (max-width: 1050px) {
        .salary-overview {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .salary-overview-item:nth-child(3) {
            border-right: 0;
        }

        .salary-overview-item:nth-child(n + 4) {
            border-top: 1px solid #e2ecee;
        }

        .salary-container .assessment-info {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px) {
        .salary-container {
            width: calc(100% - 18px);
        }

        .salary-container .header-section {
            min-height: 0;
            padding: 20px;
        }

        .salary-container .header-text h1 {
            font-size: 22px;
        }

        .salary-overview,
        .salary-container .user-info,
        .salary-container .assessment-info,
        .salary-container .salary-breakdown {
            grid-template-columns: minmax(0, 1fr);
        }

        .salary-overview-item,
        .salary-overview-item:nth-child(3) {
            border-top: 1px solid #e2ecee;
            border-right: 0;
        }

        .salary-overview-item:first-child {
            border-top: 0;
        }
    }
</style>

<div class="salary-container">
    <div class="header-section">
        <!--<img src="pic/salary_icon.png" alt="工资" class="salary-icon">-->
        <div class="header-text">
            <h1>考核工资</h1>
            <p>查看本期考核进度、预计工资和历史发放记录</p>
        </div>
    </div>

    <?php if (get_user_class() >= UC_SYSOP): ?>
        <div class="salary-admin-actions">
            <a href="staff_assessment.php" class="salary-admin-link">工资与考核配置</a>
        </div>
    <?php endif; ?>

    <?php if ($assessment_info): ?>
        <div class="salary-overview">
            <div class="salary-overview-item">
                <span>当前岗位</span>
                <strong><?= htmlspecialchars($assessment_info['assessment_name']) ?></strong>
            </div>
            <div class="salary-overview-item">
                <span>考核周期</span>
                <strong><?= format_interval_seconds((int)$requirement_info['interval_seconds']) ?></strong>
            </div>
            <div class="salary-overview-item">
                <span>基础工资</span>
                <strong><?= number_format((int)$requirement_info['base_salary']) ?></strong>
            </div>
            <div class="salary-overview-item is-total">
                <span>本期预计工资</span>
                <strong><?= number_format((int)($salary_info['total_salary'] ?? 0)) ?></strong>
            </div>
            <div class="salary-overview-item is-status">
                <span>发放状态</span>
                <strong><?= $can_claim ? '等待系统发放' : ($requirement_ok ? '考核周期内' : '尚未达到最低要求') ?></strong>
            </div>
        </div>
    <?php endif; ?>

    <div class="user-info">
        <h3>用户信息</h3>
        <p><strong>用户名：</strong><?= get_username($CURUSER['id']) ?></p>
        <p><strong>用户ID：</strong><?= $CURUSER['id'] ?></p>
        <p><strong>用户等级：</strong><?= get_user_class_name(get_user_class(), false, true, true) ?></p>
        <p><strong>用户头衔：</strong><?= htmlspecialchars($CURUSER['title'] ?: '无') ?></p>
     
    </div>

    <?php if ($assessment_info): ?>
        <div class="assessment-info">
            <h3>当前考核信息</h3>
            <p>
                <strong>考核类型（禁止恶意刷数据）：</strong>
                <?= htmlspecialchars($assessment_info['assessment_name']) ?> - <?= htmlspecialchars($assessment_info['config_name']) ?>
                <span class="explain-tip">（<span class="explain-text">不同身份要求不一样，同一身份不同时间进组要求也可能不一样，具体看招募信息页</span>）</span>
            </p>
            <p>
                <strong>基础工资：</strong>
                <?= number_format($requirement_info['base_salary']) ?> 魔力值
                <span class="explain-tip">（<span class="explain-text">低于最低要求无法获得基本工资</span>）</span>
            </p>
            <p>
                <strong>考核周期：</strong>
                <?php echo format_interval_seconds($requirement_info['interval_seconds']); ?>
                <span class="explain-tip">（<span class="explain-text">决定领取工资间隔以及作为考核期内数据统计</span>）</span>
            </p>

            <?php if ($requirement_info['assessment_type'] == 1): // 发布员/转种员 
            ?>

                <p>
                    <strong>发布数量要求：</strong>
                    <?= number_format($requirement_info['publish_quantity_req']) ?> 个
                    <span class="explain-tip">（<span class="explain-text">用于计算额外奖励</span>）</span>
                </p>
                <p>
                    <strong>最低发布数量要求：</strong>
                    <?= number_format($requirement_info['min_publish_quantity_req']) ?> 个
                    <span class="explain-tip">（<span class="explain-text">低于此值无法获得基本工资</span>）</span>
                </p>
                <p>
                    <strong>发布体积要求：</strong>
                    <?= mksize($requirement_info['publish_volume_req'] * 1024 * 1024 * 1024) ?> 
                    <span class="explain-tip">（<span class="explain-text">用于计算额外奖励</span>）</span>
                </p>
                <p>
                    <strong>最低发布体积要求：</strong>
                    <?= mksize($requirement_info['min_publish_volume_req'] * 1024 * 1024 * 1024) ?> 
                    <span class="explain-tip">（<span class="explain-text">低于此值无法获得基本工资</span>）</span>
                </p>
                <p>
                    <strong>超过要求奖励：</strong>
                    <?= number_format($requirement_info['publish_bonus_amount']) ?> 魔力值 / （<?= $requirement_info['publish_bonus_qty_ratio'] ?>个+<?= mksize($requirement_info['publish_bonus_vol_ratio'] * 1024 * 1024 * 1024) ?>）
                    <span class="explain-tip">（<span class="explain-text">数量体积需同时满足取最小值</span>）</span>
                </p>
                <p>
                    <strong>最多获得超出要求奖励：</strong>
                    <?= number_format($requirement_info['publish_max_bonus']) ?> 魔力值
                    <span class="explain-tip">（<span class="explain-text">超出最多要求奖励不累计</span>）</span>
                </p>

            <?php elseif ($requirement_info['assessment_type'] == 2): // 保种员 
            ?>

                <p>
                    <strong>保种数量要求：</strong>
                    <?= number_format($requirement_info['seed_quantity_req']) ?> 个
                    <span class="explain-tip">（<span class="explain-text">用于计算额外奖励</span>）</span>
                </p>
                <p>
                    <strong>最低保种数量要求：</strong>
                    <?= number_format($requirement_info['min_seed_quantity_req']) ?> 个
                    <span class="explain-tip">（<span class="explain-text">低于此值无法获得基本工资</span>）</span>
                </p>
                <p>
                    <strong>保种体积要求：</strong>
                    <?= mksize($requirement_info['seed_volume_req'] * 1024 * 1024 * 1024) ?> 
                    <span class="explain-tip">（<span class="explain-text">用于计算额外奖励</span>）</span>
                </p>
                <p>
                    <strong>最低保种体积要求：</strong>
                    <?= mksize($requirement_info['min_seed_volume_req'] * 1024 * 1024 * 1024) ?> 
                    <span class="explain-tip">（<span class="explain-text">低于此值无法获得基本工资</span>）</span>
                </p>
                <p>
                    <strong>超过要求奖励：</strong>
                    <?= number_format($requirement_info['seed_bonus_amount']) ?> 魔力值 / （<?= $requirement_info['seed_bonus_qty_ratio'] ?>个+<?= mksize($requirement_info['seed_bonus_vol_ratio'] * 1024 * 1024 * 1024) ?>）
                    <span class="explain-tip">（<span class="explain-text">数量体积需同时满足取最小值</span>）</span>
                </p>
                <p>
                    <strong>最多获得超出要求奖励：</strong>
                    <?= number_format($requirement_info['seed_max_bonus']) ?> 魔力值
                    <span class="explain-tip">（<span class="explain-text">超出最多要求奖励不累计</span>）</span>
                </p>

            <?php elseif ($requirement_info['assessment_type'] == 3): // 审核员 
            ?>

                <p>
                    <strong>操作总数（包括拒绝，通过，编辑）要求：</strong>
                    <?= number_format($requirement_info['audit_total_req']) ?> 次
                    <span class="explain-tip">（<span class="explain-text">用于计算额外奖励</span>）</span>
                </p>
                <p>
                    <strong>最低操作总数要求：</strong>
                    <?= number_format($requirement_info['min_audit_total_req']) ?> 次
                    <span class="explain-tip">（<span class="explain-text">低于此值无法获得基本工资</span>）</span>
                </p>
                <p>
                    <strong>通过审核要求：</strong>
                    <?= number_format($requirement_info['audit_pass_req']) ?> 次
                    <span class="explain-tip">（<span class="explain-text">用于计算额外奖励</span>）</span>
                </p>
                <p>
                    <strong>最低通过审核要求：</strong>
                    <?= number_format($requirement_info['min_audit_pass_req']) ?> 次
                    <span class="explain-tip">（<span class="explain-text">低于此值无法获得基本工资</span>）</span>
                </p>
                <p>
                    <strong>超过要求奖励：</strong>
                    <?= number_format($requirement_info['audit_bonus_amount']) ?> 魔力值 / （<?= $requirement_info['audit_bonus_total_ratio'] ?>次+<?= $requirement_info['audit_bonus_pass_ratio'] ?>次）
                    <span class="explain-tip">（<span class="explain-text">操作总数通过审核需同时满足取最小值</span>）</span>
                </p>
                <p>
                    <strong>最多获得超出要求奖励：</strong>
                    <?= number_format($requirement_info['audit_max_bonus']) ?> 魔力值
                    <span class="explain-tip">（<span class="explain-text">超出最多要求奖励不累计</span>）</span>
                </p>

            <?php elseif ($requirement_info['assessment_type'] == 4): // 其他 
            ?>

            <?php endif; ?>

            <style>
                .explain-tip {
                    color: #888;
                    font-size: 13px;
                    margin-left: 4px;
                }
                .explain-text {
                    background: #f3f3f3;
                    border-radius: 3px;
                    padding: 1px 5px;
                }
            </style>
        </div>

        <?php if ($salary_info): ?>
            <div class="stats-section">
                <h3>考核数据统计</h3>
                <?php
                $stats = $salary_info['stats'];
                $req = $salary_info['requirement'];
                ?>

                <?php if ($req['assessment_type'] == 1): // 发布员/转种员 
                ?>
                    <table class="stats-table">
                        <tr>
                            <th>项目</th>
                            <th>历史总计</th>
                            <th class="period-column">考核期内（过去<?php echo format_interval_seconds($requirement_info['interval_seconds']); ?>）</th>
                            <th>考核要求</th>
                            <th class="min-requirement-column">最低要求</th>
                            <th>状态</th>
                        </tr>
                        <tr>
                            <td>发布数量</td>
                            <td><?= number_format($stats['pub']['count']) ?></td>
                            <td class="period-column"><?= number_format($stats['pub']['count_past']) ?></td>
                            <td><?= number_format($req['publish_quantity_req']) ?></td>
                            <td class="min-requirement-column"><?= number_format($req['min_publish_quantity_req']) ?></td>
                            <td style="color: <?= $stats['pub']['count_past'] >= $req['min_publish_quantity_req'] ? '#28a745' : '#dc3545' ?>;">
                                <?= $stats['pub']['count_past'] >= $req['min_publish_quantity_req'] ? '✅ 达标' : '❌ 未达标' ?>
                            </td>
                        </tr>
                        <tr>
                            <td>发布体积</td>
                            <td><?= mksize($stats['pub']['total']) ?></td>
                            <td class="period-column"><?= mksize($stats['pub']['total_past']) ?></td>
                            <td><?= mksize($req['publish_volume_req'] * 1024 * 1024 * 1024) ?></td>
                            <td class="min-requirement-column"><?= mksize($req['min_publish_volume_req'] * 1024 * 1024 * 1024) ?></td>
                            <td style="color: <?= $stats['pub']['total_past'] >= ($req['min_publish_volume_req'] * 1024 * 1024 * 1024) ? '#28a745' : '#dc3545' ?>;">
                                <?= $stats['pub']['total_past'] >= ($req['min_publish_volume_req'] * 1024 * 1024 * 1024) ? '✅ 达标' : '❌ 未达标' ?>
                            </td>
                        </tr>
                    </table>

                    <?php
                    // 判断是否达到领取额外工资的要求：考核期内数据大于考核要求
                    $excess_count = $stats['pub']['count_past'] - $req['publish_quantity_req'];
                    $excess_volume_gb = ($stats['pub']['total_past'] / (1024 * 1024 * 1024)) - $req['publish_volume_req'];
                    $can_get_bonus = ($excess_count > 0) && ($excess_volume_gb > 0);
                    ?>
                    <?php if ($salary_info['bonus_salary'] > 0 && $can_get_bonus): ?>
                        <div class="bonus-calculation">
                            <h4>📊 额外奖励计算过程</h4>
                            <?php
                            $bonus_units = min(
                                floor($excess_count / $req['publish_bonus_qty_ratio']),
                                floor($excess_volume_gb / $req['publish_bonus_vol_ratio'])
                            );
                            ?>
                            <div class="calculation-step">
                                <strong>1. 计算超出数量：</strong><br>
                                超出发布数量 = 考核期内数量 - 考核要求 = <?= number_format($stats['pub']['count_past']) ?> - <?= number_format($req['publish_quantity_req']) ?> = <?= number_format($excess_count) ?> 个
                            </div>
                            <div class="calculation-step">
                                <strong>2. 计算超出体积：</strong><br>
                                超出发布体积 = 考核期内体积 - 考核要求 = <?= mksize($stats['pub']['total_past']) ?> - <?= mksize($req['publish_volume_req'] * 1024 * 1024 * 1024) ?> = <?= number_format($excess_volume_gb, 2) ?> GB
                            </div>
                            <div class="calculation-step">
                                <strong>3. 计算奖励单位：</strong><br>
                                数量奖励单位 = 超出数量 ÷ 数量比例 = <?= number_format($excess_count) ?> ÷ <?= $req['publish_bonus_qty_ratio'] ?> = <?= floor($excess_count / $req['publish_bonus_qty_ratio']) ?> 单位<br>
                                体积奖励单位 = 超出体积 ÷ 体积比例 = <?= number_format($excess_volume_gb, 2) ?> GB ÷ <?= number_format($req['publish_bonus_vol_ratio'], 2) ?> GB = <?= floor($excess_volume_gb / $req['publish_bonus_vol_ratio']) ?> 单位<br>
                                取最小值：<?= $bonus_units ?> 单位
                            </div>
                            <div class="calculation-step">
                                <strong>4. 计算奖励金额：</strong><br>
                                奖励金额 = 奖励单位 × 基础奖励 = <?= $bonus_units ?> × <?= number_format($req['publish_bonus_amount']) ?> = <?= number_format($bonus_units * $req['publish_bonus_amount']) ?> 魔力值
                            </div>
                            <?php if ($bonus_units * $req['publish_bonus_amount'] > $req['publish_max_bonus']): ?>
                                <div class="calculation-step">
                                    <strong>5. 应用奖励上限：</strong><br>
                                    计算奖励金额 <?= number_format($bonus_units * $req['publish_bonus_amount']) ?> 超过了最多获得超出要求奖励 <?= number_format($req['publish_max_bonus']) ?> 魔力值<br>
                                    因此最终奖励被限制为：<?= number_format($req['publish_max_bonus']) ?> 魔力值
                                </div>
                            <?php endif; ?>
                            <div class="calculation-result">
                                🎉 最终额外奖励：<?= number_format($salary_info['bonus_salary']) ?> 魔力值
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bonus-calculation">
                            <h4>📊 额外奖励计算过程</h4>
                            <div class="calculation-result" style="color:#dc3545;">
                                未达到领取额外工资的要求（考核期内发布数量和体积均需超过考核要求）。
                            </div>
                        </div>
                    <?php endif; ?>

                <?php elseif ($req['assessment_type'] == 2): // 保种员 
                ?>
                    <table class="stats-table">
                        <tr>
                            <th>项目</th>
                            <th>历史总计</th>
                            <th class="period-column">考核期内（过去<?php echo format_interval_seconds($requirement_info['interval_seconds']); ?>）</th>
                            <th>考核要求</th>
                            <th class="min-requirement-column">最低要求</th>
                            <th>状态</th>
                        </tr>
                        <tr>
                            <td>保种数量</td>
                            <td><?= number_format($stats['seed']['count']) ?></td>
                            <td class="period-column"><?= number_format($stats['seed']['count_past']) ?></td>
                            <td><?= number_format($req['seed_quantity_req']) ?></td>
                            <td class="min-requirement-column"><?= number_format($req['min_seed_quantity_req']) ?></td>
                            <td style="color: <?= $stats['seed']['count_past'] >= $req['min_seed_quantity_req'] ? '#28a745' : '#dc3545' ?>;">
                                <?= $stats['seed']['count_past'] >= $req['min_seed_quantity_req'] ? '✅ 达标' : '❌ 未达标' ?>
                            </td>
                        </tr>
                        <tr>
                            <td>保种体积</td>
                            <td><?= mksize($stats['seed']['total']) ?></td>
                            <td class="period-column"><?= mksize($stats['seed']['total_past']) ?></td>
                            <td><?= mksize($req['seed_volume_req'] * 1024 * 1024 * 1024) ?></td>
                            <td class="min-requirement-column"><?= mksize($req['min_seed_volume_req'] * 1024 * 1024 * 1024) ?></td>
                            <td style="color: <?= $stats['seed']['total_past'] >= ($req['min_seed_volume_req'] * 1024 * 1024 * 1024) ? '#28a745' : '#dc3545' ?>;">
                                <?= $stats['seed']['total_past'] >= ($req['min_seed_volume_req'] * 1024 * 1024 * 1024) ? '✅ 达标' : '❌ 未达标' ?>
                            </td>
                        </tr>
                    </table>

                    <?php
                    // 判断是否达到领取额外工资的要求：考核期内数量和体积均需超过考核要求
                    if (
                        $stats['seed']['count_past'] > $req['seed_quantity_req']
                        && $stats['seed']['total_past'] > ($req['seed_volume_req'] * 1024 * 1024 * 1024)
                        && $salary_info['bonus_salary'] > 0
                    ): ?>
                        <div class="bonus-calculation">
                            <h4>📊 额外奖励计算过程</h4>
                            <?php
                            $excess_count = $stats['seed']['count_past'] - $req['seed_quantity_req'];
                            $excess_volume_gb = ($stats['seed']['total_past'] / (1024 * 1024 * 1024)) - $req['seed_volume_req'];
                            $bonus_units = min(
                                floor($excess_count / $req['seed_bonus_qty_ratio']),
                                floor($excess_volume_gb / $req['seed_bonus_vol_ratio'])
                            );
                            ?>
                            <div class="calculation-step">
                                <strong>1. 计算超出数量：</strong><br>
                                超出保种数量 = 考核期内数量 - 考核要求 = <?= number_format($stats['seed']['count_past']) ?> - <?= number_format($req['seed_quantity_req']) ?> = <?= number_format($excess_count) ?> 个
                            </div>
                            <div class="calculation-step">
                                <strong>2. 计算超出体积：</strong><br>
                                超出保种体积 = 考核期内体积 - 考核要求 = <?= mksize($stats['seed']['total_past']) ?> - <?= mksize($req['seed_volume_req'] * 1024 * 1024 * 1024) ?> = <?= number_format($excess_volume_gb, 2) ?> GB
                            </div>
                            <div class="calculation-step">
                                <strong>3. 计算奖励单位：</strong><br>
                                数量奖励单位 = 超出数量 ÷ 数量比例 = <?= number_format($excess_count) ?> ÷ <?= $req['seed_bonus_qty_ratio'] ?> = <?= floor($excess_count / $req['seed_bonus_qty_ratio']) ?> 单位<br>
                                体积奖励单位 = 超出体积 ÷ 体积比例 = <?= number_format($excess_volume_gb, 2) ?> GB ÷ <?= number_format($req['seed_bonus_vol_ratio'], 2) ?> GB = <?= floor($excess_volume_gb / $req['seed_bonus_vol_ratio']) ?> 单位<br>
                                取最小值：<?= $bonus_units ?> 单位
                            </div>
                            <div class="calculation-step">
                                <strong>4. 计算奖励金额：</strong><br>
                                奖励金额 = 奖励单位 × 基础奖励 = <?= $bonus_units ?> × <?= number_format($req['seed_bonus_amount']) ?> = <?= number_format($bonus_units * $req['seed_bonus_amount']) ?> 魔力值
                            </div>
                            <?php if ($bonus_units * $req['seed_bonus_amount'] > $req['seed_max_bonus']): ?>
                                <div class="calculation-step">
                                    <strong>5. 应用奖励上限：</strong><br>
                                    计算奖励金额 <?= number_format($bonus_units * $req['seed_bonus_amount']) ?> 超过了最多获得超出要求奖励 <?= number_format($req['seed_max_bonus']) ?> 魔力值<br>
                                    因此最终奖励被限制为：<?= number_format($req['seed_max_bonus']) ?> 魔力值
                                </div>
                            <?php endif; ?>
                            <div class="calculation-result">
                                🎉 最终额外奖励：<?= number_format($salary_info['bonus_salary']) ?> 魔力值
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bonus-calculation">
                            <h4>📊 额外奖励计算过程</h4>
                            <div class="calculation-result" style="color:#dc3545;">
                                未达到领取额外工资的要求（考核期内保种数量和体积均需超过考核要求）。
                            </div>
                        </div>
                    <?php endif; ?>

                <?php elseif ($req['assessment_type'] == 3): // 审核员 
                ?>
                    <table class="stats-table">
                        <tr>
                            <th>项目</th>
                            <th>历史总计</th>
                            <th class="period-column">考核期内（过去<?php echo format_interval_seconds($requirement_info['interval_seconds']); ?>）</th>
                            <th>考核要求</th>
                            <th class="min-requirement-column">最低要求</th>
                            <th>状态</th>
                        </tr>
                        <tr>
                            <td>操作总数</td>
                            <td><?= number_format($stats['alloperation']['count']) ?></td>
                            <td class="period-column"><?= number_format($stats['alloperation']['count_past']) ?></td>
                            <td><?= number_format($req['audit_total_req']) ?></td>
                            <td class="min-requirement-column"><?= number_format($req['min_audit_total_req']) ?></td>
                            <td style="color: <?= $stats['alloperation']['count_past'] >= $req['min_audit_total_req'] ? '#28a745' : '#dc3545' ?>;">
                                <?= $stats['alloperation']['count_past'] >= $req['min_audit_total_req'] ? '✅ 达标' : '❌ 未达标' ?>
                            </td>
                        </tr>
                        <tr>
                            <td>通过审核</td>
                            <td><?= number_format($stats['approveoperation']['count']) ?></td>
                            <td class="period-column"><?= number_format($stats['approveoperation']['count_past']) ?></td>
                            <td><?= number_format($req['audit_pass_req']) ?></td>
                            <td class="min-requirement-column"><?= number_format($req['min_audit_pass_req']) ?></td>
                            <td style="color: <?= $stats['approveoperation']['count_past'] >= $req['min_audit_pass_req'] ? '#28a745' : '#dc3545' ?>;">
                                <?= $stats['approveoperation']['count_past'] >= $req['min_audit_pass_req'] ? '✅ 达标' : '❌ 未达标' ?>
                            </td>
                        </tr>
                    </table>

                    <?php if ($salary_info['bonus_salary'] > 0): ?>
                        <div class="bonus-calculation">
                            <h4>📊 额外奖励计算过程</h4>
                            <?php
                            $excess_total = $stats['alloperation']['count_past'] - $req['audit_total_req'];
                            $excess_pass = $stats['approveoperation']['count_past'] - $req['audit_pass_req'];
                            $bonus_units = min(
                                floor($excess_total / $req['audit_bonus_total_ratio']),
                                floor($excess_pass / $req['audit_bonus_pass_ratio'])
                            );
                            ?>
                            <div class="calculation-step">
                                <strong>1. 计算超出操作总数：</strong><br>
                                超出操作总数 = 考核期内操作总数 - 考核要求 = <?= number_format($stats['alloperation']['count_past']) ?> - <?= number_format($req['audit_total_req']) ?> = <?= number_format($excess_total) ?> 次
                            </div>
                            <div class="calculation-step">
                                <strong>2. 计算超出通过审核：</strong><br>
                                超出通过审核 = 考核期内通过审核 - 考核要求 = <?= number_format($stats['approveoperation']['count_past']) ?> - <?= number_format($req['audit_pass_req']) ?> = <?= number_format($excess_pass) ?> 次
                            </div>
                            <div class="calculation-step">
                                <strong>3. 计算奖励单位：</strong><br>
                                操作总数奖励单位 = 超出操作总数 ÷ 操作总数比例 = <?= number_format($excess_total) ?> ÷ <?= $req['audit_bonus_total_ratio'] ?> = <?= floor($excess_total / $req['audit_bonus_total_ratio']) ?> 单位<br>
                                通过审核奖励单位 = 超出通过审核 ÷ 通过审核比例 = <?= number_format($excess_pass) ?> ÷ <?= $req['audit_bonus_pass_ratio'] ?> = <?= floor($excess_pass / $req['audit_bonus_pass_ratio']) ?> 单位<br>
                                取最小值：<?= $bonus_units ?> 单位
                            </div>
                            <div class="calculation-step">
                                <strong>4. 计算奖励金额：</strong><br>
                                奖励金额 = 奖励单位 × 基础奖励 = <?= $bonus_units ?> × <?= number_format($req['audit_bonus_amount']) ?> = <?= number_format($bonus_units * $req['audit_bonus_amount']) ?> 魔力值
                            </div>
                            <?php if ($bonus_units * $req['audit_bonus_amount'] > $req['audit_max_bonus']): ?>
                                <div class="calculation-step">
                                    <strong>5. 应用奖励上限：</strong><br>
                                    计算奖励金额 <?= number_format($bonus_units * $req['audit_bonus_amount']) ?> 超过了最多获得超出要求奖励 <?= number_format($req['audit_max_bonus']) ?> 魔力值<br>
                                    因此最终奖励被限制为：<?= number_format($req['audit_max_bonus']) ?> 魔力值
                                </div>
                            <?php endif; ?>
                            <div class="calculation-result">
                                🎉 最终额外奖励：<?= number_format($salary_info['bonus_salary']) ?> 魔力值
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bonus-calculation">
                            <h4>📊 额外奖励计算过程</h4>
                            <div class="calculation-result" style="color:#dc3545;">
                                未达到领取额外工资的要求（考核期内操作总数和通过审核均需大于考核要求）。
                            </div>
                        </div>
                    <?php endif; ?>

                <?php elseif ($req['assessment_type'] == 4): // 其他 
                ?>
                    <p>其他类型考核，无需特殊要求，达到时间间隔即可领取基础工资。</p>
                <?php endif; ?>
            </div>

            <div class="salary-breakdown">
                <h3>工资明细</h3>
                <p><strong>基础工资：</strong><?= number_format($salary_info['base_salary']) ?> 魔力值</p>
                <?php if ($salary_info['bonus_salary'] > 0): ?>
                    <p><strong>额外奖励：</strong><?= number_format($salary_info['bonus_salary']) ?> 魔力值</p>
                <?php endif; ?>
                <p><strong>总计可领取：</strong><span style="color: #28a745; font-weight: bold; font-size: 18px;"><?= number_format($salary_info['total_salary']) ?> 魔力值</span></p>
            </div>
        <?php endif; ?>

        <div class="salary-section">
            <h3>工资领取</h3>
            <div class="salary-amount">
                本次可领取：<?= $salary_info ? number_format($salary_info['total_salary']) : '0' ?> 魔力值
            </div>

            <div class="claim-status <?= $can_claim ? 'can-claim' : 'cannot-claim' ?>">
                <?php if ($can_claim): ?>
                    ✅ 可以领取工资
                <?php else: ?>
                    ❌ 暂时无法领取
                    <?php if (!$time_ok): ?>
                        <br>时间间隔：<?= $remaining_time_text ?>
                    <?php endif; ?>
                    <?php if (!$requirement_ok): ?>
                        <br>考核要求：未达到最低考核要求
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <p><strong>当前魔力值：</strong><span id="user-bonus"><?= number_format((int)$CURUSER['seedbonus']) ?></span></p>


            <!-- <button class="salary-btn" id="claim-salary-btn" <?= $can_claim ? '' : 'disabled' ?> onclick="claimSalary()">
                <?= $can_claim ? '立即领取工资' : '暂时无法领取' ?>
            </button> -->


            <button class="salary-btn" id="claim-salary-btn" disabled>
                主动领取关闭（系统将按时发放）
            </button>

            <div id="claim-result" style="margin-top: 15px; font-size: 16px;"></div>
        </div>

    <?php else: ?>
        <div class="no-assessment">
            <h3>无考核分配</h3>
            <p>您当前没有分配考核，无法领取工资。</p>
            <p>请联系站长为您分配考核。</p>
        </div>
    <?php endif; ?>

    <?php if (get_user_class() >= UC_SYSOP): ?>
        <div class="history-section" style="margin-top: 30px;">
            <h3>🛡️ 管理员视图 （运维及以上级别） - 全部考核与工资概况</h3>
            <div style="margin:8px 0; color:#666; font-size:13px;">
                显示前
                <a href="view_salary.php?admin_limit=100" class="btn btn-sm">100</a>
                <a href="view_salary.php?admin_limit=200" class="btn btn-sm">200</a>
                <a href="view_salary.php?admin_limit=500" class="btn btn-sm">500</a>
                （当前：<?= isset($_GET['admin_limit']) ? (int)$_GET['admin_limit'] : 200 ?>）
            </div>
            <?php if (empty($admin_assessments)): ?>
                <p style="color:#6c757d;font-style:italic;">暂无分配考核的用户</p>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="history-table">
                        <tr>
                            <th>UID</th>
                            <th>用户名</th>
                            <th>考核类型</th>
                            <th>配置</th>
                            <th>周期</th>
                            <th>可领基础</th>
                            <th>可领奖励</th>
                            <th>合计可领</th>
                            <th>达标</th>
                            <th>时间OK</th>
                            <th>上次领取</th>
                            <th>上次金额</th>
                            <th>下次可领时间</th>
                        </tr>
                        <?php foreach ($admin_assessments as $a): ?>
                            <tr>
                                <td><a href="userdetails.php?id=<?= (int)$a['uid'] ?>" target="_blank"><?= (int)$a['uid'] ?></a></td>
                                <td><?= htmlspecialchars($a['username'] ?: '未知') ?></td>
                                <td><?= htmlspecialchars($a['assessment_name']) ?></td>
                                <td><?= htmlspecialchars($a['config_name']) ?></td>
                                <td><?= format_interval_seconds($a['interval_seconds']) ?></td>
                                <td><?= number_format($a['base_salary']) ?></td>
                                <td><?= number_format($a['bonus_salary']) ?></td>
                                <td style="font-weight:bold;color:#28a745;">&plus;<?= number_format($a['total_salary']) ?></td>
                                <td style="color: <?= $a['requirement_ok'] ? '#28a745' : '#dc3545' ?>;"><?= $a['requirement_ok'] ? '✅' : '❌' ?></td>
                                <td style="color: <?= $a['time_ok'] ? '#28a745' : '#dc3545' ?>;"><?= $a['time_ok'] ? '✅' : '⏳' ?></td>
                                <td><?= $a['last_claim'] ? htmlspecialchars($a['last_claim']) : '-' ?></td>
                                <td><?= $a['last_amount'] !== null ? number_format($a['last_amount']) : '-' ?></td>
                                <td><?= $a['next_claim_at'] ? htmlspecialchars($a['next_claim_at']) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="history-section">
        <h3>📋 领取历史记录</h3>
        <?php if (empty($history)): ?>
            <p style="color: #6c757d; font-style: italic;">暂无领取记录</p>
        <?php else: ?>
            <table class="history-table">
                <tr>
                    <th>领取时间</th>
                    <th>领取金额</th>
                    <th>领取前魔力</th>
                    <th>领取后魔力</th>
                    <th>魔力增加</th>
                </tr>
                <?php foreach ($history as $record): ?>
                    <tr>
                        <td><?= htmlspecialchars($record['created_at']) ?></td>
                        <td><?= number_format($record['salary_amount']) ?></td>
                        <td><?= number_format($record['bonus_before']) ?></td>
                        <td><?= number_format($record['bonus_after']) ?></td>
                        <td style="color: #28a745; font-weight: bold;">+<?= number_format($record['salary_amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
    function claimSalary() {
        const btn = document.getElementById('claim-salary-btn');
        const resultDiv = document.getElementById('claim-result');

        btn.disabled = true;
        btn.textContent = '领取中...';
        resultDiv.textContent = '';

        fetch('view_salary.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'action=claim_salary'
        }).then(r => r.json()).then(res => {
            if (res.success) {
                resultDiv.innerHTML = '<span style="color: #28a745; font-weight: bold;">' + res.message + '</span>';
                document.getElementById('user-bonus').textContent = res.bonus_after.toLocaleString();
                btn.textContent = '领取成功';
                btn.style.background = '#6c757d';

                // 3秒后刷新页面显示最新记录
                setTimeout(() => {
                    location.reload();
                }, 3000);
            } else {
                resultDiv.innerHTML = '<span style="color: #dc3545;">' + res.message + '</span>';
                btn.disabled = false;
                btn.textContent = '立即领取工资';
            }
        }).catch(error => {
            resultDiv.innerHTML = '<span style="color: #dc3545;">领取失败，请稍后重试</span>';
            btn.disabled = false;
            btn.textContent = '立即领取工资';
        });
    }
</script>

<?php
stdfoot();
?>
<style id="codex-salary-polish">
.salary-container{max-width:1400px;margin:24px auto;padding:0 20px;color:#243447}
.salary-admin-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:16px 0;padding:14px 18px;background:#f6f9fc;border:1px solid #e3ebf3;border-radius:10px}
.salary-admin-link{background:#1677ff!important;color:#fff!important;border-radius:7px!important;padding:9px 14px!important;font-weight:600}
.user-info,.no-assessment,.history-table{background:#fff;border:1px solid #e6edf5;border-radius:12px;box-shadow:0 4px 18px rgba(30,65,100,.06);padding:20px;margin:16px 0}
.no-assessment{border-left:4px solid #f0a43c;background:#fffaf1}.history-table{overflow:auto}.history-table table{min-width:920px;border-collapse:separate;border-spacing:0}.history-table th{position:sticky;top:0;background:#f3f7fb;color:#496179;font-weight:700}.history-table th,.history-table td{padding:11px 12px;border-bottom:1px solid #edf1f5;white-space:nowrap}.history-table tbody tr:hover{background:#f8fbff}
@media(max-width:800px){.salary-container{padding:0 12px;margin:14px auto}.user-info,.no-assessment,.history-table{padding:14px;border-radius:9px}}</style>