<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('此脚本仅允许由服务器计划任务执行。');
}

// 使用相对路径，避免硬编码绝对路径
require_once(__DIR__ . '/../include/bittorrent.php');
dbconn(true);

// Prevent overlapping cron runs from paying the same salary period twice.
$lock_result = sql_query("SELECT GET_LOCK('novahd_auto_salary', 0) AS acquired");
$lock_row = mysql_fetch_assoc($lock_result);
if ((int)($lock_row['acquired'] ?? 0) !== 1) {
    exit("已有工资发放任务正在运行，本次执行已跳过。\n");
}
register_shutdown_function(static function () {
    sql_query("SELECT RELEASE_LOCK('novahd_auto_salary')");
});
// 关闭超时限制
set_time_limit(0);

// 添加缺失的get_date_time函数
function get_date_time() {
    return date('Y-m-d H:i:s');
}

// 发送站内信函数 - 修复了未读消息字段问题
function send_pm($sender_id, $receiver_id, $subject, $message) {
    global $DB;
    
    if (empty($receiver_id) || empty($subject) || empty($message)) {
        return false;
    }
    
    $subject = sqlesc(trim($subject));
    $message = sqlesc(trim($message));
    $sender_id = (int)$sender_id;
    $receiver_id = (int)$receiver_id;
    $added = sqlesc(get_date_time());
    
    // 插入站内信（已移除status字段）
    sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) 
               VALUES ($sender_id, $receiver_id, $subject, $message, $added)");
    
    return mysql_affected_rows() > 0;
}

// 用户种子统计函数（与 view_salary 原始逻辑对齐）
function get_user_torrent_stats($user_id, $interval_seconds) {
    global $DB;
    
    $user_id = (int)$user_id;
    $interval_seconds = (int)$interval_seconds;
    $time_ago = time() - $interval_seconds;
    $time_ago_sql = sqlesc(date('Y-m-d H:i:s', $time_ago));
    
    $stats = [
        'pub' => [ 
            'count_past' => 0,
            'total_past' => 0
        ],
        'seed' => [
            'count_past' => 0,
            'total_past' => 0
        ],
        'alloperation' => [
            'count_past' => 0
        ],
        'approveoperation' => [
            'count_past' => 0
        ]
    ];
    
    // 发布统计（过去 interval_seconds 内）
    $res = sql_query("SELECT COUNT(*) as count, SUM(size) as total 
                     FROM torrents 
                     WHERE owner = $user_id 
                     AND added >= $time_ago_sql");
    $row = mysql_fetch_assoc($res);
    if ($row) {
        $stats['pub']['count_past'] = (int)$row['count'];
        $stats['pub']['total_past'] = (int)$row['total'];
    }
    
    // 保种统计（过去 interval_seconds 内开始做种）
    $res = sql_query("SELECT p.torrent, t.size, p.started
                     FROM peers p
                     LEFT JOIN torrents t ON p.torrent = t.id
                     WHERE p.userid = $user_id
                     AND p.seeder = 1");
    $now_ts = time();
    $past_ts = $now_ts - $interval_seconds;
    $count_past = 0;
    $total_past = 0;
    while ($row = mysql_fetch_assoc($res)) {
        $started = isset($row['started']) ? strtotime($row['started']) : 0;
        if ($started >= $past_ts) {
            $count_past++;
            $total_past += (int)$row['size'];
        }
    }
    $stats['seed']['count_past'] = $count_past;
    $stats['seed']['total_past'] = $total_past;
    
    // 审核操作统计（过去 interval_seconds 内）
    $res = sql_query("SELECT COUNT(*) AS cnt 
                     FROM torrent_operation_logs 
                     WHERE uid = $user_id 
                     AND (action_type = 'approval_allow' OR action_type = 'approval_deny' OR action_type = 'edit')
                     AND updated_at >= $time_ago_sql");
    $row = mysql_fetch_assoc($res);
    $stats['alloperation']['count_past'] = (int)($row['cnt'] ?? 0);
    
    $res = sql_query("SELECT COUNT(*) AS cnt 
                     FROM torrent_operation_logs 
                     WHERE uid = $user_id 
                     AND action_type = 'approval_allow'
                     AND updated_at >= $time_ago_sql");
    $row = mysql_fetch_assoc($res);
    $stats['approveoperation']['count_past'] = (int)($row['cnt'] ?? 0);
    
    return $stats;
}

// 获取所有有考核任务的用户
$users_sql = "SELECT uaa.uid, u.username, ar.* 
              FROM user_assessment_assignments uaa
              LEFT JOIN assessment_requirements ar ON uaa.requirement_id = ar.id
              LEFT JOIN users u ON uaa.uid = u.id";
$users_res = sql_query($users_sql);

// 系统发送者ID
$system_sender_id = 1;

while ($user = mysql_fetch_assoc($users_res)) {
    $uid = (int)$user['uid'];
    $username = $user['username'];
    $interval_seconds = (int)$user['interval_seconds'];
    $assessment_type = (int)$user['assessment_type'];
    
    // 检查时间间隔
    $last_claim_sql = "SELECT created_at FROM uploader_salary_log 
                      WHERE user_id = " . sqlesc($uid) . " 
                      ORDER BY created_at DESC LIMIT 1";
    $last_claim_res = sql_query($last_claim_sql);
    $last_claim_row = mysql_fetch_assoc($last_claim_res);
    
    $time_ok = true;
    if ($last_claim_row) {
        $last_claim_time = strtotime($last_claim_row['created_at']);
        $current_time = time();
        $time_diff = $current_time - $last_claim_time;
        
        if ($time_diff < $interval_seconds) {
            $time_ok = false;
        }
    }
    
    // 检查考核要求并发放工资
    if ($time_ok) {
        $stats = get_user_torrent_stats($uid, $interval_seconds);
        $can_claim = false;
        $base_salary = 0;
        $bonus_salary = 0;
        $total_salary = 0;
        
        switch ($assessment_type) {
            case 1: // 发布员/转种员
                $min_pub_qty = isset($user['min_publish_quantity_req']) ? (int)$user['min_publish_quantity_req'] : 0;
                $min_pub_vol = isset($user['min_publish_volume_req']) ? (int)$user['min_publish_volume_req'] : 0;
                
                if ($stats['pub']['count_past'] >= $min_pub_qty && 
                    $stats['pub']['total_past'] >= $min_pub_vol * 1024 * 1024 * 1024) {
                    $can_claim = true;
                    $base_salary = isset($user['base_salary']) ? (int)$user['base_salary'] : 0;
                    
                    $base_qty = isset($user['publish_quantity_req']) ? (int)$user['publish_quantity_req'] : $min_pub_qty;
                    $base_vol = isset($user['publish_volume_req']) ? (int)$user['publish_volume_req'] : $min_pub_vol;
                    
                    if ($stats['pub']['count_past'] > $base_qty && 
                        $stats['pub']['total_past'] > $base_vol * 1024 * 1024 * 1024) {
                        
                        $excess_count = $stats['pub']['count_past'] - $base_qty;
                        $excess_volume_gb = ($stats['pub']['total_past'] / (1024 * 1024 * 1024)) - $base_vol;
                        
                        $bonus_qty_ratio = max(1, isset($user['publish_bonus_qty_ratio']) ? (int)$user['publish_bonus_qty_ratio'] : 1);
                        $bonus_vol_ratio = max(1, isset($user['publish_bonus_vol_ratio']) ? (int)$user['publish_bonus_vol_ratio'] : 1);
                        
                        $bonus_units = min(
                            floor($excess_count / $bonus_qty_ratio),
                            floor($excess_volume_gb / $bonus_vol_ratio)
                        );
                        
                        $bonus_amount = isset($user['publish_bonus_amount']) ? (int)$user['publish_bonus_amount'] : 0;
                        $max_bonus = isset($user['publish_max_bonus']) ? (int)$user['publish_max_bonus'] : PHP_INT_MAX;
                        
                        $bonus_salary = min($bonus_units * $bonus_amount, $max_bonus);
                    }
                }
                break;
                
            case 2: // 保种员
                $min_seed_qty = isset($user['min_seed_quantity_req']) ? (int)$user['min_seed_quantity_req'] : 0;
                $min_seed_vol = isset($user['min_seed_volume_req']) ? (int)$user['min_seed_volume_req'] : 0;
                
                if ($stats['seed']['count_past'] >= $min_seed_qty && 
                    $stats['seed']['total_past'] >= $min_seed_vol * 1024 * 1024 * 1024) {
                    $can_claim = true;
                    $base_salary = isset($user['base_salary']) ? (int)$user['base_salary'] : 0;
                    
                    $base_qty = isset($user['seed_quantity_req']) ? (int)$user['seed_quantity_req'] : $min_seed_qty;
                    $base_vol = isset($user['seed_volume_req']) ? (int)$user['seed_volume_req'] : $min_seed_vol;
                    
                    if ($stats['seed']['count_past'] > $base_qty && 
                        $stats['seed']['total_past'] > $base_vol * 1024 * 1024 * 1024) {
                        
                        $excess_count = $stats['seed']['count_past'] - $base_qty;
                        $excess_volume_gb = ($stats['seed']['total_past'] / (1024 * 1024 * 1024)) - $base_vol;
                        
                        $bonus_qty_ratio = max(1, isset($user['seed_bonus_qty_ratio']) ? (int)$user['seed_bonus_qty_ratio'] : 1);
                        $bonus_vol_ratio = max(1, isset($user['seed_bonus_vol_ratio']) ? (int)$user['seed_bonus_vol_ratio'] : 1);
                        
                        $bonus_units = min(
                            floor($excess_count / $bonus_qty_ratio),
                            floor($excess_volume_gb / $bonus_vol_ratio)
                        );
                        
                        $bonus_amount = isset($user['seed_bonus_amount']) ? (int)$user['seed_bonus_amount'] : 0;
                        $max_bonus = isset($user['seed_max_bonus']) ? (int)$user['seed_max_bonus'] : PHP_INT_MAX;
                        
                        $bonus_salary = min($bonus_units * $bonus_amount, $max_bonus);
                    }
                }
                break;
                
            case 3: // 审核员
                $min_audit_total = isset($user['min_audit_total_req']) ? (int)$user['min_audit_total_req'] : 0;
                $min_audit_pass = isset($user['min_audit_pass_req']) ? (int)$user['min_audit_pass_req'] : 0;
                
                if ($stats['alloperation']['count_past'] >= $min_audit_total && 
                    $stats['approveoperation']['count_past'] >= $min_audit_pass) {
                    $can_claim = true;
                    $base_salary = isset($user['base_salary']) ? (int)$user['base_salary'] : 0;
                    
                    $base_total = isset($user['audit_total_req']) ? (int)$user['audit_total_req'] : $min_audit_total;
                    $base_pass = isset($user['audit_pass_req']) ? (int)$user['audit_pass_req'] : $min_audit_pass;
                    
                    if ($stats['alloperation']['count_past'] > $base_total && 
                        $stats['approveoperation']['count_past'] > $base_pass) {
                        
                        $excess_total = $stats['alloperation']['count_past'] - $base_total;
                        $excess_pass = $stats['approveoperation']['count_past'] - $base_pass;
                        
                        $bonus_total_ratio = max(1, isset($user['audit_bonus_total_ratio']) ? (int)$user['audit_bonus_total_ratio'] : 1);
                        $bonus_pass_ratio = max(1, isset($user['audit_bonus_pass_ratio']) ? (int)$user['audit_bonus_pass_ratio'] : 1);
                        
                        $bonus_units = min(
                            floor($excess_total / $bonus_total_ratio),
                            floor($excess_pass / $bonus_pass_ratio)
                        );
                        
                        $bonus_amount = isset($user['audit_bonus_amount']) ? (int)$user['audit_bonus_amount'] : 0;
                        $max_bonus = isset($user['audit_max_bonus']) ? (int)$user['audit_max_bonus'] : PHP_INT_MAX;
                        
                        $bonus_salary = min($bonus_units * $bonus_amount, $max_bonus);
                    }
                }
                break;
                
            case 4: // 其他
                $can_claim = true;
                $base_salary = isset($user['base_salary']) ? (int)$user['base_salary'] : 0;
                break;
        }
        
        $total_salary = $base_salary + $bonus_salary;
        
        if ($can_claim && $total_salary > 0) {
            // 发放工资逻辑
            $user_info = mysql_fetch_assoc(sql_query("SELECT seedbonus FROM users WHERE id = " . sqlesc($uid)));
            $bonus_before = (int)$user_info['seedbonus'];
            $bonus_after = $bonus_before + $total_salary;
            
            // 更新用户魔力值
            sql_query("UPDATE users SET seedbonus = " . sqlesc($bonus_after) . " WHERE id = " . sqlesc($uid));
            
            // 记录工资日志
            sql_query("INSERT INTO uploader_salary_log (user_id, username, salary_amount, bonus_before, bonus_after, created_at) 
                      VALUES (" . sqlesc($uid) . ", " . sqlesc($username) . ", " . sqlesc($total_salary) . ", " . sqlesc($bonus_before) . ", " . sqlesc($bonus_after) . ", " . sqlesc(get_date_time()) . ")");
            
            // 发送站内信
            $payment_date = date('Y-m-d');
            $salary_month = date('Y年m月', strtotime('-1 month', strtotime($payment_date)));
            
            $subject = "{$salary_month}工资已发放";
            $message = "亲爱的用户 {$username}，您好！
            您{$salary_month}的考核工资已成功发放，具体明细如下：
            基本工资：{$base_salary} 魔力值
            额外奖励：{$bonus_salary} 魔力值
            总计发放：{$total_salary} 魔力值
            当前您的魔力值余额为：{$bonus_after}
            感谢您对站点的贡献！";
            
            $pm_success = send_pm($system_sender_id, $uid, $subject, $message);
            
            // 记录日志
            if ($pm_success) {
                error_log("[$payment_date] 给用户ID:{$uid}({$username})发送工资站内信成功");
            } else {
                error_log("[$payment_date] 给用户ID:{$uid}({$username})发送工资站内信失败");
            }
        }
    }
}

echo "自动发放完成\n";
?>
