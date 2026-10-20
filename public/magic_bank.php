<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
parked();

// 银行配置 - 期限(月) => 年利率(%)
// 适度调整的利率，保持期限越长利率越高的原则
$bank_config = [
    1 => 1.4,    // 1个月
    3 => 1.4,    // 3个月
    6 => 1.6,    // 6个月
    12 => 1.7,   // 1年
    36 => 2.15,   // 3年
    60 => 2.75   // 5年
];

// 转换期限为显示文本
$term_text_map = [
    1 => '1个月',
    3 => '3个月',
    6 => '6个月',
    12 => '1年',
    36 => '3年',
    60 => '5年'
];

/**
 * 检查并结算所有到期存款
 */
function auto_settle_matured_deposits() {
    global $CURUSER;
    
    // 获取当前用户所有未结算且已到期的存款
    $res = sql_query("SELECT * FROM magic_bank WHERE user_id = " . sqlesc($CURUSER['id']) . 
                    " AND status = 'deposited' AND mature_time <= NOW()");
    
    while ($deposit = mysql_fetch_assoc($res)) {
        // 计算利息
        $interest = $deposit['amount'] * ($deposit['interest_rate'] / 100) * ($deposit['term'] / 12);
        $total = $deposit['amount'] + $interest;
        
        // 更新用户魔力值
        sql_query("UPDATE users SET seedbonus = seedbonus + " . sqlesc($total) . 
                " WHERE id = " . sqlesc($CURUSER['id']));
        
        // 更新存款状态
        sql_query("UPDATE magic_bank SET status = 'matured', settled_time = NOW(), " .
                "interest = " . sqlesc($interest) . ", total = " . sqlesc($total) .
                " WHERE id = " . sqlesc($deposit['id']));
        
        // 记录日志
        write_log("用户 " . $CURUSER['username'] . " 的存款 #" . $deposit['id'] . 
                " 已自动结算，本金: " . $deposit['amount'] . ", 利息: " . $interest, 'bank');
    }
}

/**
 * 初始化银行表（如果不存在）
 */
function init_bank_table() {
    // 检查表格是否存在（使用系统已有数据库函数）
    $res = sql_query("SHOW TABLES LIKE 'magic_bank'");
    $tableExists = mysql_num_rows($res) > 0;
    
    if (!$tableExists) {
        // 创建表格
        $sql = "CREATE TABLE `magic_bank` (
          `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(10) unsigned NOT NULL,
          `amount` decimal(10,1) NOT NULL,
          `term` int(11) NOT NULL COMMENT '存款期限(月)',
          `interest_rate` decimal(5,2) NOT NULL COMMENT '年利率(%)',
          `deposit_time` datetime NOT NULL,
          `mature_time` datetime NOT NULL,
          `withdraw_time` datetime DEFAULT NULL,
          `settled_time` datetime DEFAULT NULL,
          `interest` decimal(10,1) DEFAULT 0,
          `total` decimal(10,1) DEFAULT 0,
          `status` enum('deposited','withdrawn','matured') NOT NULL DEFAULT 'deposited',
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `status` (`status`),
          KEY `mature_time` (`mature_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        sql_query($sql);
    }
}

// 初始化银行表
init_bank_table();

// 自动结算到期存款
auto_settle_matured_deposits();

$action = $_GET['action'] ?? '';
$msg = '';

// 处理存款
if ($action == 'deposit' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);
    $term = intval($_POST['term'] ?? 0);
    
    // 验证输入
    if ($amount <= 0) {
        $msg = "存款金额必须大于0";
    } elseif (!isset($bank_config[$term])) {
        $msg = "无效的存款期限";
    } elseif ($amount > $CURUSER['seedbonus']) {
        $msg = "魔力值不足，无法存款";
    } else {
        // 计算到期时间
        $deposit_time = date('Y-m-d H:i:s');
        $mature_time = date('Y-m-d H:i:s', strtotime("+$term months"));
        $interest_rate = $bank_config[$term];
        
        // 扣除用户魔力值
        sql_query("UPDATE users SET seedbonus = seedbonus - " . sqlesc($amount) . 
                " WHERE id = " . sqlesc($CURUSER['id']));
        
        // 记录存款
        sql_query("INSERT INTO magic_bank (user_id, amount, term, interest_rate, deposit_time, mature_time) 
                VALUES (" . sqlesc($CURUSER['id']) . ", " . sqlesc($amount) . ", " . sqlesc($term) . 
                ", " . sqlesc($interest_rate) . ", " . sqlesc($deposit_time) . ", " . sqlesc($mature_time) . ")");
        
        $msg = "存款成功！您已存入 " . $amount . " 魔力值，期限 " . $term_text_map[$term] . 
                "，年利率 " . $interest_rate . "%";
        
        write_log("用户 " . $CURUSER['username'] . " 存入 " . $amount . " 魔力值，期限 " . $term . "个月", 'bank');
    }
}

// 处理取款
if ($action == 'withdraw' && isset($_GET['id'])) {
    $deposit_id = intval($_GET['id']);
    
    // 获取存款信息
    $res = sql_query("SELECT * FROM magic_bank WHERE id = " . sqlesc($deposit_id) . 
                    " AND user_id = " . sqlesc($CURUSER['id']));
    $deposit = mysql_fetch_assoc($res);
    
    if (!$deposit) {
        $msg = "无效的存款记录";
    } elseif ($deposit['status'] != 'deposited') {
        $msg = "该存款已处理，无法重复操作";
    } else {
        $now = date('Y-m-d H:i:s');
        $is_mature = strtotime($now) >= strtotime($deposit['mature_time']);
        
        if ($is_mature) {
            // 到期取款，计算利息
            $interest = $deposit['amount'] * ($deposit['interest_rate'] / 100) * ($deposit['term'] / 12);
            $total = $deposit['amount'] + $interest;
            
            // 更新用户魔力值
            sql_query("UPDATE users SET seedbonus = seedbonus + " . sqlesc($total) . 
                    " WHERE id = " . sqlesc($CURUSER['id']));
            
            // 更新存款状态
            sql_query("UPDATE magic_bank SET status = 'withdrawn', withdraw_time = NOW(), " .
                    "interest = " . sqlesc($interest) . ", total = " . sqlesc($total) .
                    " WHERE id = " . sqlesc($deposit_id));
            
            $msg = "取款成功！您的存款 " . $deposit['amount'] . " 魔力值已到期，获得利息 " . 
                    $interest . " 魔力值，总计取出 " . $total . " 魔力值";
        } else {
            // 提前取款，无利息
            $total = $deposit['amount'];
            
            // 更新用户魔力值
            sql_query("UPDATE users SET seedbonus = seedbonus + " . sqlesc($total) . 
                    " WHERE id = " . sqlesc($CURUSER['id']));
            
            // 更新存款状态
            sql_query("UPDATE magic_bank SET status = 'withdrawn', withdraw_time = NOW(), " .
                    "interest = 0, total = " . sqlesc($total) .
                    " WHERE id = " . sqlesc($deposit_id));
            
            $msg = "提前取款成功！您取出了本金 " . $total . " 魔力值，由于提前取款，未获得利息";
        }
        
        write_log("用户 " . $CURUSER['username'] . " 取出存款 #" . $deposit_id . 
                "，金额: " . $total, 'bank');
    }
}

// 获取用户当前魔力值
$current_balance = number_format($CURUSER['seedbonus'], 1);

// 获取用户存款记录
$deposits = [];
$res = sql_query("SELECT * FROM magic_bank WHERE user_id = " . sqlesc($CURUSER['id']) . 
                " ORDER BY deposit_time DESC");
while ($row = mysql_fetch_assoc($res)) {
    $deposits[] = $row;
}

stdhead("魔力值银行");
?>

<div class="main_column" style="max-width: 1000px; margin: 0 auto; padding: 20px;">
    <h1 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;">魔力值银行</h1>
    
    <?php if ($msg): ?>
    <div style="background-color: #e8f4fd; border: 1px solid #3498db; color: #2c3e50; padding: 10px; margin: 10px 0; border-radius: 4px;">
        <?php echo $msg; ?>
    </div>
    <?php endif; ?>
    
    <div class="balance_info" style="background-color: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 4px; border-left: 4px solid #2ecc71;">
        <h3 style="margin: 0; color: #27ae60;">您当前的魔力值余额: <?php echo $current_balance; ?></h3>
    </div>
    
    <div class="deposit_form" style="background-color: white; padding: 20px; margin: 15px 0; border-radius: 4px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h2 style="color: #2c3e50; margin-top: 0;">存入魔力值</h2>
        <form action="?action=deposit" method="post">
            <table class="bank_table" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 10px; text-align: right; font-weight: bold; width: 30%;">存款金额:</td>
                    <td style="padding: 10px;">
                        <input type="number" name="amount" min="1" step="0.1" required 
                               style="padding: 8px; width: 200px; border: 1px solid #ddd; border-radius: 4px;"> 
                        魔力值
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px; text-align: right; font-weight: bold;">存款期限:</td>
                    <td style="padding: 10px;">
                        <select name="term" required 
                                style="padding: 8px; width: 200px; border: 1px solid #ddd; border-radius: 4px; background-color: white;">
                            <?php foreach ($bank_config as $term => $rate): ?>
                            <option value="<?php echo $term; ?>">
                                <?php echo $term_text_map[$term] . " (年利率 " . $rate . "%)"; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 10px; text-align: center;">
                        <input type="submit" value="确认存款" 
                               style="background-color: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 16px;">
                    </td>
                </tr>
            </table>
        </form>
    </div>
    
    <div class="deposit_records" style="background-color: white; padding: 20px; margin: 15px 0; border-radius: 4px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h2 style="color: #2c3e50; margin-top: 0;">我的存款记录</h2>
        <?php if (empty($deposits)): ?>
        <p style="padding: 10px; color: #7f8c8d;">您暂无存款记录</p>
        <?php else: ?>
        <table class="records_table" style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <tr class="header" style="background-color: #f8f9fa;">
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">存款时间</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">金额</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">期限</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">利率</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">到期时间</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">状态</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">操作</th>
            </tr>
            <?php foreach ($deposits as $deposit): ?>
            <tr style="border-bottom: 1px solid #f1f1f1;">
                <td style="padding: 12px;"><?php echo $deposit['deposit_time']; ?></td>
                <td style="padding: 12px;"><?php echo $deposit['amount']; ?> 魔力值</td>
                <td style="padding: 12px;"><?php echo $term_text_map[$deposit['term']]; ?></td>
                <td style="padding: 12px;"><?php echo $deposit['interest_rate']; ?>%</td>
                <td style="padding: 12px;"><?php echo $deposit['mature_time']; ?></td>
                <td style="padding: 12px;">
                    <?php 
                    switch ($deposit['status']) {
                        case 'deposited':
                            if (strtotime($deposit['mature_time']) <= time()) {
                                echo '<span style="color: #27ae60; font-weight: bold;">已到期</span>';
                            } else {
                                echo '<span style="color: #3498db;">存款中</span>';
                            }
                            break;
                        case 'withdrawn':
                            echo '<span style="color: #7f8c8d;">已取出</span>';
                            break;
                        case 'matured':
                            echo '<span style="color: #f39c12;">已自动结算</span>';
                            break;
                    }
                    ?>
                </td>
                <td style="padding: 12px;">
                    <?php if ($deposit['status'] == 'deposited'): ?>
                    <a href="?action=withdraw&id=<?php echo $deposit['id']; ?>" 
                       onclick="return confirm('确定要取出这笔存款吗？<?php 
                           echo (strtotime($deposit['mature_time']) > time()) ? '提前取出将不会获得利息！' : '';
                       ?>')"
                       style="color: #e74c3c; text-decoration: none; font-weight: bold;">
                        取出
                    </a>
                    <?php else: ?>
                    -
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
    
    <div class="bank_rules" style="background-color: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 4px;">
        <h2 style="color: #2c3e50; margin-top: 0;">银行规则</h2>
        <ul style="line-height: 1.6; color: #34495e;">
            <li>存款期限越长，年利率越高</li>
            <li>到期自动结算本金和利息到您的魔力值账户</li>
            <li>如果在存款到期前取出，将不计算利息，仅返还本金</li>
            <li>存款期间，魔力值不可用于其他用途</li>
            <li>利息计算公式：利息 = 本金 × 年利率 ÷ 100 × (存款月数 ÷ 12)</li>
        </ul>
    </div>
</div>

<?php
stdfoot();
?>
    