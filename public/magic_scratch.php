<?php
require_once('../include/bittorrent.php');
dbconn();
loggedinorreturn();
parked();

// 刮刮乐配置（新增每日次数限制）
$scratch_config = [
    'cost' => 5000.0,          // 每次刮奖消耗魔力值
    'daily_limit' => 5,        // 每日最大刮奖次数
    'prizes' => [              // 奖项配置：[魔力值, 中奖概率%, 奖项等级]
        0.0 => [40, '未中奖'],          // 未中奖 60%
        500.0 => [30, '五等奖'],        // 五等奖 500魔力值 15%
        1000.0 => [20, '四等奖'],       // 四等奖 1000魔力值 10%
        3000.0 => [5, '三等奖'],        // 三等奖 3000魔力值 8%
        5000.0 => [4, '二等奖'],        // 二等奖 5000魔力值 5%
        100000.0 => [1, '一等奖']       // 一等奖 100000魔力值 2%
    ],
    'scratch_width' => 300,    // 刮刮区域宽度
    'scratch_height' => 150    // 刮刮区域高度
];

// 检查今日刮奖次数是否已达上限
function check_daily_limit($user_id, $limit) {
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');
    
    $res = sql_query("SELECT COUNT(*) AS count FROM magic_scratch 
                     WHERE user_id = " . sqlesc($user_id) . " 
                     AND scratch_time BETWEEN " . sqlesc($today_start) . " AND " . sqlesc($today_end));
    $row = mysql_fetch_assoc($res);
    
    return [
        'reached' => $row['count'] >= $limit,
        'used' => $row['count'],
        'limit' => $limit
    ];
}

// 初始化刮刮乐记录表
function init_scratch_table() {
    $res = sql_query("SHOW TABLES LIKE 'magic_scratch'");
    $tableExists = mysql_num_rows($res) > 0;
    
    if (!$tableExists) {
        $sql = "CREATE TABLE `magic_scratch` (
          `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(10) unsigned NOT NULL,
          `cost` decimal(10,1) NOT NULL COMMENT '消耗魔力值',
          `prize` decimal(10,1) NOT NULL COMMENT '中奖魔力值',
          `prize_level` varchar(20) NOT NULL COMMENT '奖项等级',
          `scratch_time` datetime NOT NULL COMMENT '购买刮奖资格时间',
          `settle_time` datetime DEFAULT NULL COMMENT '结算时间',
          `status` enum('unscratched','scratched','settled') NOT NULL DEFAULT 'unscratched' COMMENT '状态：未刮开/已刮开/已结算',
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `status` (`status`),
          KEY `scratch_time` (`scratch_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        sql_query($sql);
    }
}

// 购买刮奖资格（增加次数限制检查）
function buy_scratch资格(&$CURUSER, $config) {
    $limit_check = check_daily_limit($CURUSER['id'], $config['daily_limit']);
    if ($limit_check['reached']) {
        return [
            'success' => false,
            'msg' => "今日刮奖次数已达上限（每日限" . $config['daily_limit'] . "次），请明天再试！"
        ];
    }
    
    if ($CURUSER['seedbonus'] < $config['cost']) {
        return [
            'success' => false,
            'msg' => "魔力值不足！购买刮奖资格需消耗 " . $config['cost'] . " 魔力值，您当前余额：" . $CURUSER['seedbonus']
        ];
    }
    
    sql_query("UPDATE users SET seedbonus = seedbonus - " . sqlesc($config['cost']) . 
            " WHERE id = " . sqlesc($CURUSER['id']));
    
    $CURUSER['seedbonus'] = $CURUSER['seedbonus'] - $config['cost'];
    
    sql_query("INSERT INTO magic_scratch (user_id, cost, prize, prize_level, scratch_time, status) 
            VALUES (" . sqlesc($CURUSER['id']) . ", " . sqlesc($config['cost']) . ", 0.0, '', " . 
            sqlesc(date('Y-m-d H:i:s')) . ", 'unscratched')");
    
    $scratch_id = mysql_insert_id();
    
    write_log("用户 " . $CURUSER['username'] . " 购买刮奖资格，消耗 " . $config['cost'] . " 魔力值，刮奖ID：" . $scratch_id, 'scratch');
    
    return [
        'success' => true,
        'msg' => "购买成功！请在下方刮奖区域滑动刮开涂层",
        'scratch_id' => $scratch_id
    ];
}

// 刮开涂层
function scratch_open($scratch_id, $config) {
    global $CURUSER;
    
    $res = sql_query("SELECT * FROM magic_scratch WHERE id = " . sqlesc($scratch_id) . 
                    " AND user_id = " . sqlesc($CURUSER['id']) . " AND status = 'unscratched'");
    $record = mysql_fetch_assoc($res);
    
    if (!$record) {
        return [
            'success' => false,
            'msg' => "无效的刮奖记录或已刮开"
        ];
    }
    
    $random = mt_rand(1, 100);
    $prize = 0.0;
    $prize_level = '未中奖';
    $cumulative_rate = 0;
    
    foreach ($config['prizes'] as $p => $info) {
        list($rate, $level) = $info;
        $cumulative_rate += $rate;
        if ($random <= $cumulative_rate) {
            $prize = $p;
            $prize_level = $level;
            break;
        }
    }
    
    sql_query("UPDATE magic_scratch SET prize = " . sqlesc($prize) . ", prize_level = " . sqlesc($prize_level) . 
            ", status = 'scratched' WHERE id = " . sqlesc($scratch_id));
    
    return [
        'success' => true,
        'prize' => $prize,
        'prize_level' => $prize_level,
        'scratch_id' => $scratch_id
    ];
}

// 结算中奖魔力值
function settle_prize($scratch_id) {
    global $CURUSER;
    
    $res = sql_query("SELECT * FROM magic_scratch WHERE id = " . sqlesc($scratch_id) . 
                    " AND user_id = " . sqlesc($CURUSER['id']) . " AND status = 'scratched'");
    $record = mysql_fetch_assoc($res);
    
    if (!$record) {
        return [
            'success' => false,
            'msg' => "无效的记录或已结算"
        ];
    }
    
    $prize = $record['prize'];
    
    if ($prize > 0) {
        sql_query("UPDATE users SET seedbonus = seedbonus + " . sqlesc($prize) . 
                " WHERE id = " . sqlesc($CURUSER['id']));
        
        $CURUSER['seedbonus'] = $CURUSER['seedbonus'] + $prize;
        
        $msg = "恭喜！" . $record['prize_level'] . " 结算成功，获得 " . $prize . " 魔力值！";
    } else {
        $msg = "未中奖，无魔力值可结算";
    }
    
    sql_query("UPDATE magic_scratch SET settle_time = " . sqlesc(date('Y-m-d H:i:s')) . 
            ", status = 'settled' WHERE id = " . sqlesc($scratch_id));
    
    write_log("用户 " . $CURUSER['username'] . " 刮奖ID " . $scratch_id . " 结算，" . 
            ($prize > 0 ? "中奖 " . $prize . " 魔力值" : "未中奖") , 'scratch');
    
    return [
        'success' => true,
        'msg' => $msg,
        'prize' => $prize
    ];
}

// 清空用户刮奖记录
function clear_scratch_records($user_id) {
    global $CURUSER;
    sql_query("DELETE FROM magic_scratch WHERE user_id = " . sqlesc($user_id));
    write_log("用户 " . $CURUSER['username'] . " 清空了所有刮奖记录", 'scratch');
    return [
        'success' => true,
        'msg' => "所有刮奖记录已成功清空"
    ];
}

// 初始化数据表
init_scratch_table();

// 处理操作请求
$action = $_GET['action'] ?? '';
$msg = '';
$buy_result = null;
$scratch_result = null;
$settle_result = null;
$clear_result = null;
$current_scratch_id = null;
$daily_limit_info = check_daily_limit($CURUSER['id'], $scratch_config['daily_limit']);

// 1. 购买刮奖资格
if ($action == 'buy' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $buy_result = buy_scratch资格($CURUSER, $scratch_config);
    $msg = $buy_result['msg'];
    $current_scratch_id = $buy_result['scratch_id'] ?? null;
    // 重新获取次数信息
    $daily_limit_info = check_daily_limit($CURUSER['id'], $scratch_config['daily_limit']);
}

// 2. 刮开涂层（AJAX请求）
if ($action == 'scratch' && isset($_POST['scratch_id'])) {
    $scratch_id = intval($_POST['scratch_id']);
    $scratch_result = scratch_open($scratch_id, $scratch_config);
    header('Content-Type: application/json');
    echo json_encode($scratch_result);
    exit;
}

// 3. 结算中奖魔力值
if ($action == 'settle' && isset($_GET['scratch_id'])) {
    $scratch_id = intval($_GET['scratch_id']);
    $settle_result = settle_prize($scratch_id);
    $msg = $settle_result['msg'];
    $current_scratch_id = $scratch_id;
}

// 4. 清空刮奖记录
if ($action == 'clear_records' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $clear_result = clear_scratch_records($CURUSER['id']);
    $msg = $clear_result['msg'];
}

// 获取用户当前魔力值
$current_balance = number_format($CURUSER['seedbonus'], 1);

// 分页获取刮奖记录
$page = intval($_GET['page'] ?? 1);
$page = max(1, $page);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 记录总数
$count_res = sql_query("SELECT COUNT(*) AS total FROM magic_scratch WHERE user_id = " . sqlesc($CURUSER['id']));
$count_row = mysql_fetch_assoc($count_res);
$total_records = $count_row['total'];
$total_pages = max(1, ceil($total_records / $per_page));

// 获取当前页记录
$scratch_records = [];
$res = sql_query("SELECT * FROM magic_scratch WHERE user_id = " . sqlesc($CURUSER['id']) . 
                " ORDER BY scratch_time DESC LIMIT $offset, $per_page");
while ($row = mysql_fetch_assoc($res)) {
    $scratch_records[] = $row;
}

stdhead("魔力值刮刮乐");
?>

<div class="main_column" style="max-width: 800px; margin: 0 auto; padding: 20px;">
    <h1 style="color: #e67e22; border-bottom: 2px solid #f39c12; padding-bottom: 10px; text-align: center;">魔力值刮刮乐</h1>
    
    <?php if ($msg): ?>
    <div style="background-color: <?php 
        if (isset($settle_result['success']) && $settle_result['success'] && $settle_result['prize'] > 0) {
            echo '#e8f5e9';
        } elseif (isset($buy_result['success']) && $buy_result['success']) {
            echo '#e0f2fe';
        } elseif (isset($clear_result['success']) && $clear_result['success']) {
            echo '#e0f2fe';
        } else {
            echo '#fef2f2';
        }
    ?>; 
                border: 1px solid <?php 
        if (isset($settle_result['success']) && $settle_result['success'] && $settle_result['prize'] > 0) {
            echo '#4ade80';
        } elseif (isset($buy_result['success']) && $buy_result['success']) {
            echo '#38bdf8';
        } elseif (isset($clear_result['success']) && $clear_result['success']) {
            echo '#38bdf8';
        } else {
            echo '#f87171';
        }
    ?>; 
                color: <?php 
        if (isset($settle_result['success']) && $settle_result['success'] && $settle_result['prize'] > 0) {
            echo '#166534';
        } elseif (isset($buy_result['success']) && $buy_result['success']) {
            echo '#0c4a6e';
        } elseif (isset($clear_result['success']) && $clear_result['success']) {
            echo '#0c4a6e';
        } else {
            echo '#991b1b';
        }
    ?>;
                padding: 15px; margin: 20px 0; border-radius: 8px; text-align: center; font-size: 18px; font-weight: bold;">
        <?php echo $msg; ?>
    </div>
    <?php endif; ?>
    
    <!-- 余额信息（增加每日次数提示） -->
    <div class="balance_info" style="background-color: #f8fafc; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #3b82f6;">
        <h3 style="margin: 0; color: #1e40af; text-align: center; font-size: 22px;">
            您当前的魔力值余额: <span style="color: #e67e22; font-size: 28px;"><?php echo $current_balance; ?></span>
        </h3>
        <p style="text-align: center; margin-top: 5px; color: #64748b;">
            购买刮奖资格需消耗 <?php echo $scratch_config['cost']; ?> 魔力值
        </p>
        <p style="text-align: center; margin-top: 5px; color: #64748b;">
            今日剩余刮奖次数: <span style="color: <?php echo $daily_limit_info['reached'] ? '#ef4444' : '#10b981'; ?>">
                <?php echo $daily_limit_info['limit'] - $daily_limit_info['used']; ?>/<?php echo $daily_limit_info['limit']; ?>
            </span>
        </p>
    </div>
    
    <!-- 刮刮乐核心区域 -->
    <div class="scratch_area" style="background-color: white; padding: 30px; margin: 20px 0; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center;">
        <h2 style="color: #2c3e50; margin-top: 0; margin-bottom: 20px;">刮奖中心</h2>
        
        <?php if (!$current_scratch_id || (isset($settle_result['success']) && $settle_result['success'])): ?>
            <!-- 未购买刮奖资格：显示购买按钮（次数达上限则禁用） -->
            <form action="?action=buy" method="post" style="margin-top: 30px;">
                <button type="submit" id="buyBtn" 
                        style="background-color: <?php echo $daily_limit_info['reached'] ? '#ccc' : '#e67e22'; ?>; 
                               color: white; border: none; padding: 15px 40px; border-radius: 30px; 
                               cursor: <?php echo $daily_limit_info['reached'] ? 'not-allowed' : 'pointer'; ?>; 
                               font-size: 20px; font-weight: bold; transition: background-color 0.3s;"
                        <?php echo $daily_limit_info['reached'] ? 'disabled' : ''; ?>>
                    购买刮奖资格（消耗 <?php echo $scratch_config['cost']; ?> 魔力值）
                </button>
            </form>
        <?php else: ?>
            <!-- 已购买刮奖资格：显示刮奖区域 -->
            <div style="position: relative; width: <?php echo $scratch_config['scratch_width']; ?>px; height: <?php echo $scratch_config['scratch_height']; ?>px; margin: 0 auto;">
                <!-- 结果显示层 -->
                <div id="scratchResult" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: bold; color: <?php echo isset($scratch_result['prize_level']) && $scratch_result['prize_level'] != '未中奖' ? '#e67e22' : '#64748b'; ?>; border: 2px dashed #f39c12; border-radius: 4px;">
                    <?php if (isset($scratch_result['prize_level'])): ?>
                        <?php echo $scratch_result['prize_level']; ?>
                        <?php if ($scratch_result['prize'] > 0): ?>
                            <span style="display: block; font-size: 18px; margin-top: 10px;">
                                可获得 <?php echo $scratch_result['prize']; ?> 魔力值
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        刮开查看奖项
                    <?php endif; ?>
                </div>
                
                <!-- 刮刮涂层 -->
                <canvas id="scratchCanvas" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; cursor: pointer; border-radius: 4px; background-color: #ecf0f1; <?php echo isset($scratch_result['prize_level']) ? 'display: none;' : ''; ?>"></canvas>
            </div>
            
            <!-- 操作按钮 -->
            <div class="btn-container" style="margin-top: 20px;">
                <?php if (isset($scratch_result['prize_level']) && $scratch_result['prize'] > 0): ?>
                    <a href="?action=settle&scratch_id=<?php echo $current_scratch_id; ?>" 
                       style="display: inline-block; background-color: #10b981; color: white; border: none; padding: 12px 30px; border-radius: 30px; cursor: pointer; font-size: 18px; font-weight: bold; transition: background-color 0.3s; text-decoration: none;">
                        结算魔力值
                    </a>
                <?php elseif (isset($scratch_result['prize_level'])): ?>
                    <!-- 未中奖时显示"再刮一次"按钮（次数达上限则禁用） -->
                    <form action="?action=buy" method="post" style="display: inline;">
                        <button type="submit" 
                                style="background-color: <?php echo $daily_limit_info['reached'] ? '#ccc' : '#e67e22'; ?>; 
                                       color: white; border: none; padding: 12px 30px; border-radius: 30px; 
                                       cursor: <?php echo $daily_limit_info['reached'] ? 'not-allowed' : 'pointer'; ?>; 
                                       font-size: 18px; font-weight: bold; transition: background-color 0.3s;"
                                <?php echo $daily_limit_info['reached'] ? 'disabled' : ''; ?>>
                            再刮一次
                        </button>
                    </form>
                <?php endif; ?>
                <!-- 统一显示"返回首页"按钮 -->
                <a href="?" 
                   style="display: inline-block; background-color: #64748b; color: white; border: none; padding: 12px 30px; border-radius: 30px; cursor: pointer; font-size: 18px; font-weight: bold; transition: background-color 0.3s; text-decoration: none; margin-left: 10px;">
                    返回首页
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 中奖规则 -->
    <div class="scratch_rules" style="background-color: #f8fafc; padding: 20px; margin: 20px 0; border-radius: 8px;">
        <h2 style="color: #2c3e50; margin-top: 0;">中奖规则</h2>
        <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 15px;">
            <?php foreach ($scratch_config['prizes'] as $prize => $info): ?>
            <?php list($rate, $level) = $info; ?>
            <div style="flex: 1; min-width: 120px; background-color: white; padding: 12px; border-radius: 6px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <p style="margin: 0; font-weight: bold; color: <?php echo $prize > 0 ? '#e67e22' : '#64748b'; ?>;">
                    <?php echo $level; ?>
                </p>
                <p style="margin: 5px 0; color: #1e40af; font-size: 16px; font-weight: bold;">
                    <?php echo $prize > 0 ? '+' . $prize . ' 魔力值' : '无奖励'; ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
        <ul style="line-height: 1.8; color: #34495e; margin-top: 20px; padding-left: 20px;">
            <li>第一步：点击"购买刮奖资格"消耗魔力值，获取刮奖权限</li>
            <li>第二步：直接在刮奖区域滑动刮开涂层查看奖项等级</li>
            <li>第三步：中奖后点击"结算魔力值"，奖金自动存入您的账户</li>
            <li>未中奖可直接点击"再刮一次"继续参与（每日限<?php echo $scratch_config['daily_limit']; ?>次）</li>
            <li>每次刮奖独立计算，奖项等级随机生成</li>
        </ul>
    </div>
    
    <!-- 刮奖记录 -->
    <div class="scratch_records" style="background-color: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
        <h2 style="color: #2c3e50; margin: 0 0 15px 0;">我的刮奖记录</h2>
        
        <!-- 清空按钮单独一行 -->
        <?php if (!empty($scratch_records)): ?>
        <div style="margin-bottom: 15px; text-align: right;">
            <form action="?action=clear_records" method="post" onsubmit="return confirm('确定要清空所有刮奖记录吗？此操作不可恢复！');">
                <button type="submit" 
                        style="background-color: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 14px; transition: background-color 0.3s;">
                    清空记录
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <?php if (empty($scratch_records)): ?>
        <p style="padding: 20px; color: #64748b; text-align: center;">暂无刮奖记录，快来试试手气吧！</p>
        <?php else: ?>
        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
            <tr style="background-color: #f8fafc;">
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; color: #2c3e50;">购买时间</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; color: #2c3e50;">消耗魔力值</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; color: #2c3e50;">奖项等级</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; color: #2c3e50;">中奖魔力值</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; color: #2c3e50;">状态</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; color: #2c3e50;">结算时间</th>
            </tr>
            <?php foreach ($scratch_records as $record): ?>
            <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 12px; color: #334155;"><?php echo $record['scratch_time']; ?></td>
                <td style="padding: 12px; color: #ef4444;"><?php echo $record['cost']; ?></td>
                <td style="padding: 12px; color: <?php echo $record['prize'] > 0 ? '#e67e22' : '#64748b'; ?>; font-weight: <?php echo $record['prize'] > 0 ? 'bold' : 'normal'; ?>">
                    <?php echo $record['prize_level'] ?: '未刮开'; ?>
                </td>
                <td style="padding: 12px; color: <?php echo $record['prize'] > 0 ? '#10b981' : '#64748b'; ?>; font-weight: <?php echo $record['prize'] > 0 ? 'bold' : 'normal'; ?>">
                    <?php echo $record['prize'] > 0 ? '+' . $record['prize'] : '0.0'; ?>
                </td>
                <td style="padding: 12px;">
                    <span style="display: inline-block; padding: 4px 8px; border-radius: 4px; color: white; font-size: 14px; background-color: <?php 
                        switch($record['status']) {
                            case 'unscratched': echo '#3b82f6'; break;
                            case 'scratched': echo '#f59e0b'; break;
                            case 'settled': echo '#10b981'; break;
                            default: echo '#64748b';
                        }
                    ?>;">
                        <?php 
                        switch($record['status']) {
                            case 'unscratched': echo '未刮开'; break;
                            case 'scratched': echo '已刮开'; break;
                            case 'settled': echo '已结算'; break;
                            default: echo '未知';
                        }
                        ?>
                    </span>
                </td>
                <td style="padding: 12px; color: #64748b;">
                    <?php echo $record['settle_time'] ?: '-'; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <!-- 分页导航 -->
        <div style="margin-top: 20px; text-align: center;">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" 
                       style="display: inline-block; padding: 6px 12px; margin: 0 4px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #3498db;">
                        上一页
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" 
                       style="display: inline-block; padding: 6px 12px; margin: 0 4px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: <?php echo $i == $page ? '#fff' : '#3498db'; ?>; background-color: <?php echo $i == $page ? '#3498db' : 'transparent'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" 
                       style="display: inline-block; padding: 6px 12px; margin: 0 4px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #3498db;">
                        下一页
                    </a>
                <?php endif; ?>
            <?php endif; ?>
            <p style="margin-top: 10px; color: #64748b;">
                共 <?php echo $total_records; ?> 条记录，当前第 <?php echo $page; ?>/<?php echo $total_pages; ?> 页
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 刮刮乐交互脚本 -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('scratchCanvas');
    const ctx = canvas ? canvas.getContext('2d') : null;
    const scratchResult = document.getElementById('scratchResult');
    const currentScratchId = <?php echo $current_scratch_id ? $current_scratch_id : 'null'; ?>;
    const btnContainer = document.querySelector('.btn-container');
    
    // 初始化刮刮功能
    if (canvas && ctx && currentScratchId) {
        const canvasWidth = canvas.offsetWidth;
        const canvasHeight = canvas.offsetHeight;
        
        canvas.width = canvasWidth;
        canvas.height = canvasHeight;
        
        // 初始化刮刮涂层
        function initScratchLayer() {
            ctx.fillStyle = '#ecf0f1';
            ctx.fillRect(0, 0, canvasWidth, canvasHeight);
            
            // 绘制纹理
            ctx.fillStyle = '#bdc3c7';
            for (let i = 0; i < 300; i++) {
                const x = Math.random() * canvasWidth;
                const y = Math.random() * canvasHeight;
                const size = Math.random() * 1.5 + 0.3;
                ctx.beginPath();
                ctx.arc(x, y, size, 0, Math.PI * 2);
                ctx.fill();
            }
            
            // 绘制提示文字
            ctx.fillStyle = '#94a3b8';
            ctx.font = 'bold 20px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('刮开有奖', canvasWidth / 2, canvasHeight / 2);
            
            ctx.globalCompositeOperation = 'destination-out';
        }
        
        initScratchLayer();
        
        // 刮擦状态变量
        let isScratching = false;
        let scratchProgress = 0;
        const minScratchPercent = 0.5;
        
        // 获取坐标
        function getCanvasPos(e) {
            const rect = canvas.getBoundingClientRect();
            return {
                x: e.clientX - rect.left,
                y: e.clientY - rect.top
            };
        }
        
        // 绘制刮擦痕迹
        function drawScratch(x, y) {
            ctx.beginPath();
            ctx.arc(x, y, 20, 0, Math.PI * 2);
            ctx.fill();
            calculateScratchProgress();
        }
        
        // 计算刮开进度
        function calculateScratchProgress() {
            const imageData = ctx.getImageData(0, 0, canvasWidth, canvasHeight);
            const data = imageData.data;
            let transparentPixels = 0;
            const totalPixels = canvasWidth * canvasHeight;
            
            for (let i = 3; i < data.length; i += 4) {
                if (data[i] === 0) {
                    transparentPixels++;
                }
            }
            
            scratchProgress = transparentPixels / totalPixels;
            
            if (scratchProgress >= minScratchPercent && !window.scratchCompleted) {
                window.scratchCompleted = true;
                showScratchResult();
            }
        }
        
        // 显示刮奖结果
        function showScratchResult() {
            ctx.clearRect(0, 0, canvasWidth, canvasHeight);
            
            fetch('?action=scratch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `scratch_id=${currentScratchId}`
            })
            .then(response => {
                if (!response.ok) throw new Error('请求失败');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // 更新结果显示
                    let resultText = data.prize_level;
                    if (data.prize > 0) {
                        resultText += `<br><span style="font-size: 18px; margin-top: 10px; display: block;">可获得 ${data.prize} 魔力值</span>`;
                        scratchResult.style.color = '#e67e22';
                    } else {
                        scratchResult.style.color = '#64748b';
                    }
                    scratchResult.innerHTML = resultText;
                    
                    // 隐藏画布
                    canvas.style.display = 'none';
                    
                    // 更新按钮
                    if (btnContainer) {
                        // 获取剩余次数状态（从页面元素提取）
                        const remainingEl = document.querySelector('.balance_info p:last-child span');
                        const isLimited = remainingEl ? remainingEl.style.color === 'rgb(239, 68, 68)' : false;
                        
                        if (data.prize > 0) {
                            btnContainer.innerHTML = `
                                <a href="?action=settle&scratch_id=${data.scratch_id}" 
                                   style="display: inline-block; background-color: #10b981; color: white; border: none; padding: 12px 30px; border-radius: 30px; cursor: pointer; font-size: 18px; font-weight: bold; transition: background-color 0.3s; text-decoration: none;">
                                    结算魔力值
                                </a>
                                <a href="?" 
                                   style="display: inline-block; background-color: #64748b; color: white; border: none; padding: 12px 30px; border-radius: 30px; cursor: pointer; font-size: 18px; font-weight: bold; transition: background-color 0.3s; text-decoration: none; margin-left: 10px;">
                                    返回首页
                                </a>
                            `;
                        } else {
                            btnContainer.innerHTML = `
                                <form action="?action=buy" method="post" style="display: inline;">
                                    <button type="submit" 
                                            style="background-color: ${isLimited ? '#ccc' : '#e67e22'}; 
                                                   color: white; border: none; padding: 12px 30px; border-radius: 30px; 
                                                   cursor: ${isLimited ? 'not-allowed' : 'pointer'}; 
                                                   font-size: 18px; font-weight: bold; transition: background-color 0.3s;"
                                            ${isLimited ? 'disabled' : ''}>
                                        再刮一次
                                    </button>
                                </form>
                                <a href="?" 
                                   style="display: inline-block; background-color: #64748b; color: white; border: none; padding: 12px 30px; border-radius: 30px; cursor: pointer; font-size: 18px; font-weight: bold; transition: background-color 0.3s; text-decoration: none; margin-left: 10px;">
                                    返回首页
                                </a>
                            `;
                        }
                    }
                } else {
                    alert(data.msg || '刮奖失败，请刷新页面重试');
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('刮奖错误:', error);
                alert('网络错误，刮奖失败，请重试');
                window.location.reload();
            });
        }
        
        // 绑定鼠标事件
        canvas.addEventListener('mousedown', (e) => {
            isScratching = true;
            const pos = getCanvasPos(e);
            drawScratch(pos.x, pos.y);
        });
        
        canvas.addEventListener('mousemove', (e) => {
            if (isScratching) {
                const pos = getCanvasPos(e);
                drawScratch(pos.x, pos.y);
            }
        });
        
        ['mouseup', 'mouseleave'].forEach(event => {
            canvas.addEventListener(event, () => {
                isScratching = false;
                if (scratchProgress < minScratchPercent && !window.scratchCompleted) {
                    calculateScratchProgress();
                }
            });
        });
        
        // 触摸设备支持
        canvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            const touch = e.touches[0];
            const pos = getCanvasPos(touch);
            isScratching = true;
            drawScratch(pos.x, pos.y);
        }, { passive: false });
        
        canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            if (isScratching) {
                const touch = e.touches[0];
                const pos = getCanvasPos(touch);
                drawScratch(pos.x, pos.y);
            }
        }, { passive: false });
        
        canvas.addEventListener('touchend', () => {
            isScratching = false;
            if (scratchProgress < minScratchPercent && !window.scratchCompleted) {
                calculateScratchProgress();
            }
        });
    }
});
</script>

<?php
stdfoot();
?>