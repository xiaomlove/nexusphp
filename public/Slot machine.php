<?php
require_once('../include/bittorrent.php');
dbconn();
loggedinorreturn();
parked();

// 老虎机配置
$slot_config = [
    'cost' => 1000.0,
    'daily_limit' => 99999,
    'free_spins' => 3,
    'symbols' => [
        ['🍒', 1.5, 40],
        ['🍋', 2.0, 25],
        ['🍊', 3.0, 15],
        ['🍇', 5.0, 10],
        ['🔔', 10.0, 5],
        ['7️⃣', 20.0, 3],
        ['💰', 50.0, 1],
        ['🎯', 100.0, 0.5],
        ['💣', 0, 0.5],
    ],
    'jackpot_symbol' => '💰',
    'special_symbol' => '🎯',
    'bomb_symbol' => '💣',
    'max_multiplier' => 200,
    'bonus_round_chance' => 2,
    'near_miss_chance' => 10,  // 降低擦肩而过概率
    'bad_luck_chance' => 30,   // 增加坏运气概率
    'losing_streak_bonus' => 5,
    'small_win_threshold' => 500,
];

// 检查每日游戏次数
function check_daily_limit($user_id, $limit, $free_spins) {
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');
    
    // 总游戏次数
    $res = sql_query("SELECT COUNT(*) AS count FROM slot_machine 
                     WHERE user_id = " . sqlesc($user_id) . 
                     " AND spin_time BETWEEN " . sqlesc($today_start) . " AND " . sqlesc($today_end));
    $row = mysql_fetch_assoc($res);
    $total_spins = $row['count'];
    
    // 免费次数使用情况
    $free_res = sql_query("SELECT COUNT(*) AS free_count FROM slot_machine 
                          WHERE user_id = " . sqlesc($user_id) . 
                          " AND spin_time BETWEEN " . sqlesc($today_start) . " AND " . sqlesc($today_end) .
                          " AND cost = 0.0");
    $free_row = mysql_fetch_assoc($free_res);
    $free_used = $free_row['free_count'];
    
    $free_remaining = max(0, $free_spins - $free_used);
    
    // 统计最近连败次数
    $last_win_res = sql_query("SELECT spin_time FROM slot_machine 
                              WHERE user_id = " . sqlesc($user_id) . "
                              AND win_amount > 0
                              ORDER BY spin_time DESC 
                              LIMIT 1");
    
    $lose_streak = 0;
    if (mysql_num_rows($last_win_res) > 0) {
        $last_win_row = mysql_fetch_assoc($last_win_res);
        $last_win_time = $last_win_row['spin_time'];
        
        $streak_res = sql_query("SELECT COUNT(*) as streak FROM slot_machine 
                                WHERE user_id = " . sqlesc($user_id) . "
                                AND spin_time > " . sqlesc($last_win_time) . "
                                AND win_amount = 0");
        $streak_row = mysql_fetch_assoc($streak_res);
        $lose_streak = $streak_row['streak'] ?? 0;
    } else {
        $lose_res = sql_query("SELECT COUNT(*) as lose_count FROM slot_machine 
                              WHERE user_id = " . sqlesc($user_id) . "
                              AND win_amount = 0");
        $lose_row = mysql_fetch_assoc($lose_res);
        $lose_streak = $lose_row['lose_count'] ?? 0;
    }
    
    return [
        'total_spins' => $total_spins,
        'free_used' => $free_used,
        'free_remaining' => $free_remaining,
        'lose_streak' => $lose_streak,
        'lucky_factor' => min(100, ($lose_streak * 10)),
        'reached_limit' => false,
        'free_exhausted' => $free_remaining <= 0
    ];
}

// 初始化老虎机记录表
function init_slot_table() {
    $res = sql_query("SHOW TABLES LIKE 'slot_machine'");
    $tableExists = mysql_num_rows($res) > 0;
    
    if (!$tableExists) {
        $sql = "CREATE TABLE `slot_machine` (
          `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(10) unsigned NOT NULL,
          `cost` decimal(10,1) NOT NULL DEFAULT 0.0,
          `symbols` varchar(255) NOT NULL,
          `win_amount` decimal(10,1) NOT NULL DEFAULT 0.0,
          `multiplier` decimal(5,1) DEFAULT 1.0,
          `is_jackpot` tinyint(1) DEFAULT 0,
          `is_bonus` tinyint(1) DEFAULT 0,
          `is_near_miss` tinyint(1) DEFAULT 0,
          `is_special` tinyint(1) DEFAULT 0,
          `is_bomb` tinyint(1) DEFAULT 0,
          `is_small_win` tinyint(1) DEFAULT 0,
          `spin_time` datetime NOT NULL,
          `settle_time` datetime DEFAULT NULL,
          `status` enum('spinned','settled') NOT NULL DEFAULT 'spinned',
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `spin_time` (`spin_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        sql_query($sql);
    }
}

// 旋转老虎机
function spin_slot_machine(&$CURUSER, $config, $use_free_spin = false) {
    $limit_check = check_daily_limit($CURUSER['id'], $config['daily_limit'], $config['free_spins']);
    
    if ($use_free_spin) {
        if ($limit_check['free_remaining'] <= 0) {
            return [
                'success' => false,
                'msg' => "⚠️ 今日免费次数已用完！请使用魔力值旋转或明天再来！"
            ];
        }
    }
    
    if ($limit_check['reached_limit']) {
        return [
            'success' => false,
            'msg' => "今日游戏次数已达上限，请明天再试！"
        ];
    }
    
    $cost = $config['cost'];
    $is_free = false;
    
    if ($use_free_spin && $limit_check['free_remaining'] > 0) {
        $cost = 0.0;
        $is_free = true;
    } elseif ($CURUSER['seedbonus'] < $cost) {
        return [
            'success' => false,
            'msg' => "魔力值不足！旋转老虎机需消耗 " . $cost . " 魔力值，您当前余额：" . $CURUSER['seedbonus']
        ];
    }
    
    if (!$is_free && $cost > 0) {
        sql_query("UPDATE users SET seedbonus = seedbonus - " . sqlesc($cost) . 
                " WHERE id = " . sqlesc($CURUSER['id']));
        $CURUSER['seedbonus'] = $CURUSER['seedbonus'] - $cost;
    }
    
    $reels = [];
    $all_symbols = [];
    $luck_factor = $limit_check['lucky_factor'];
    
    for ($i = 0; $i < 3; $i++) {
        $rand = mt_rand(1, 10000) / 100;
        $cumulative = 0;
        $selected_symbol = $config['symbols'][0];
        
        foreach ($config['symbols'] as $symbol) {
            $cumulative += $symbol[2];
            if ($rand <= $cumulative) {
                $selected_symbol = $symbol;
                break;
            }
        }
        
        $reels[] = $selected_symbol;
        $all_symbols[] = $selected_symbol[0];
    }
    
    $symbols_str = implode('|', $all_symbols);
    
    $win_amount = 0.0;
    $multiplier = 1.0;
    $is_jackpot = 0;
    $is_bonus = 0;
    $is_near_miss = 0;
    $is_special = 0;
    $is_bomb = 0;
    $is_small_win = 0;
    
    $bomb_count = array_count_values($all_symbols)[$config['bomb_symbol']] ?? 0;
    if ($bomb_count > 0) {
        $is_bomb = 1;
        $win_amount = 0;
        $multiplier = 0;
    } else {
        $all_match = ($all_symbols[0] == $all_symbols[1] && $all_symbols[1] == $all_symbols[2]);
        $two_match = ($all_symbols[0] == $all_symbols[1] || $all_symbols[1] == $all_symbols[2] || $all_symbols[0] == $all_symbols[2]);
        
        $random_penalty = mt_rand(1, 100);
        $suppress_win = ($random_penalty <= $config['bad_luck_chance']);
        
        $streak_boost = $limit_check['lose_streak'] >= $config['losing_streak_bonus'];
        
        if ($all_match && !$suppress_win) {
            $symbol = $reels[0];
            
            if ($all_symbols[0] == $config['jackpot_symbol']) {
                $is_jackpot = 1;
                $win_amount = $cost * $symbol[1] * 5;
                $multiplier = $symbol[1] * 5;
            } 
            elseif ($all_symbols[0] == $config['special_symbol']) {
                $is_special = 1;
                $win_amount = $cost * $symbol[1] * 10;
                $multiplier = $symbol[1] * 10;
            }
            else {
                $win_amount = $cost * $symbol[1] * 2;
                $multiplier = $symbol[1] * 2;
            }
        }
        elseif ($two_match && !$suppress_win) {
            $matching_symbol = $all_symbols[0] == $all_symbols[1] ? $reels[0] : 
                              ($all_symbols[1] == $all_symbols[2] ? $reels[1] : $reels[0]);
            
            $win_amount = $cost * $matching_symbol[1] * 0.5;
            $multiplier = $matching_symbol[1] * 0.5;
            $is_small_win = 1;
            
            if ($streak_boost) {
                $win_amount *= 1.5;
                $multiplier *= 1.5;
            }
        }
        
        if (($all_match && $suppress_win) || ($two_match && $suppress_win) || 
            mt_rand(1, 100) <= $config['near_miss_chance'] + $luck_factor) {
            $is_near_miss = 1;
        }
        
        if (mt_rand(1, 1000) <= $config['bonus_round_chance'] && $win_amount > 0) {
            $is_bonus = 1;
            $bonus_multiplier = mt_rand(2, 5) / 10.0 + 1.0;
            $win_amount *= $bonus_multiplier;
            $multiplier *= $bonus_multiplier;
        }
    }
    
    $max_win = $cost * $config['max_multiplier'];
    if ($win_amount > $max_win) {
        $win_amount = $max_win;
    }
    
    if ($win_amount > 0 && $win_amount <= $config['small_win_threshold']) {
        $is_small_win = 1;
    }
    
    sql_query("INSERT INTO slot_machine 
              (user_id, cost, symbols, win_amount, multiplier, is_jackpot, is_bonus, 
               is_near_miss, is_special, is_bomb, is_small_win, spin_time, status) 
              VALUES (" . sqlesc($CURUSER['id']) . ", " . sqlesc($cost) . ", " . 
              sqlesc($symbols_str) . ", " . sqlesc($win_amount) . ", " . sqlesc($multiplier) . ", 
              " . sqlesc($is_jackpot) . ", " . sqlesc($is_bonus) . ", " . sqlesc($is_near_miss) . ", 
              " . sqlesc($is_special) . ", " . sqlesc($is_bomb) . ", " . sqlesc($is_small_win) . ", 
              " . sqlesc(date('Y-m-d H:i:s')) . ", 'spinned')");
    
    $spin_id = mysql_insert_id();
    
    $msg = generate_temptation_message($all_symbols, $win_amount, $is_jackpot, $is_special, 
                                     $is_bomb, $is_near_miss, $is_small_win, $limit_check['lose_streak']);
    
    $updated_limit = check_daily_limit($CURUSER['id'], $config['daily_limit'], $config['free_spins']);
    
    return [
        'success' => true,
        'spin_id' => $spin_id,
        'symbols' => $all_symbols,
        'win_amount' => $win_amount,
        'cost' => $cost,
        'multiplier' => $multiplier,
        'is_jackpot' => $is_jackpot,
        'is_bonus' => $is_bonus,
        'is_special' => $is_special,
        'is_bomb' => $is_bomb,
        'is_near_miss' => $is_near_miss,
        'is_small_win' => $is_small_win,
        'is_free' => $is_free,
        'balance' => $CURUSER['seedbonus'],
        'free_remaining' => $updated_limit['free_remaining'],
        'lose_streak' => $updated_limit['lose_streak'],
        'free_exhausted' => $updated_limit['free_remaining'] <= 0,
        'msg' => $msg
    ];
}

// 生成诱惑性消息
function generate_temptation_message($symbols, $win_amount, $is_jackpot, $is_special, 
                                   $is_bomb, $is_near_miss, $is_small_win, $lose_streak) {
    $symbols_display = implode(' ', $symbols);
    
    if ($is_bomb) {
        $messages = [
            "💣 炸弹！奖励清空！",
            "💥 炸弹！运气不佳！",
            "⚠️ 炸弹！"
        ];
    } elseif ($is_jackpot) {
        $messages = [
            "🎰 惊天大奖！",
            "💰 超级大奖！",
            "🏆 头奖！"
        ];
    } elseif ($is_special) {
        $messages = [
            "🎯 特殊大奖！",
            "✨ 奇迹出现！",
            "🌟 传说级大奖！"
        ];
    } elseif ($win_amount > 0) {
        if ($is_small_win) {
            $messages = [
                "🎊 小赚一笔！" . $symbols_display,
                "💫 赢取 " . $win_amount . " 魔力值！",
                "✨ 有收获！"
            ];
        } else {
            $messages = [
                "🎉 恭喜中奖！" . $win_amount . " 魔力值！",
                "💰 赢啦！",
                "🎁 中奖！"
            ];
        }
    } elseif ($is_near_miss) {
        $messages = [
            "😱 差一点！",
            "⚡ 擦肩而过！",
            "💔 可惜！"
        ];
    } else {
        $messages = [
            "💪 继续！",
            "✨ 再试一次！",
            "🎰 继续！"
        ];
    }
    
    return $messages[array_rand($messages)];
}

// 结算赢取金额
function settle_slot_win($spin_id) {
    global $CURUSER;
    
    $res = sql_query("SELECT * FROM slot_machine WHERE id = " . sqlesc($spin_id) . 
                    " AND user_id = " . sqlesc($CURUSER['id']) . " AND status = 'spinned'");
    $record = mysql_fetch_assoc($res);
    
    if (!$record) {
        return [
            'success' => false,
            'msg' => "无效的记录或已结算"
        ];
    }
    
    $win_amount = $record['win_amount'];
    
    if ($win_amount > 0 && !$record['is_bomb']) {
        sql_query("UPDATE users SET seedbonus = seedbonus + " . sqlesc($win_amount) . 
                " WHERE id = " . sqlesc($CURUSER['id']));
        
        $CURUSER['seedbonus'] = $CURUSER['seedbonus'] + $win_amount;
        
        $messages = [
            "💰 结算成功！获得 " . $win_amount . " 魔力值！",
            "🎉 奖励到手！" . $win_amount . " 魔力值！",
            "✨ 获得 " . $win_amount . " 魔力值！"
        ];
        $msg = $messages[array_rand($messages)];
    } else {
        $msg = "未中奖";
    }
    
    sql_query("UPDATE slot_machine SET settle_time = " . sqlesc(date('Y-m-d H:i:s')) . 
            ", status = 'settled' WHERE id = " . sqlesc($spin_id));
    
    return [
        'success' => true,
        'msg' => $msg,
        'win_amount' => $win_amount,
        'balance' => $CURUSER['seedbonus']
    ];
}

// 自动结算所有未结算记录
function auto_settle_spins($user_id) {
    global $CURUSER;
    
    $res = sql_query("SELECT * FROM slot_machine WHERE user_id = " . sqlesc($user_id) . 
                    " AND status = 'spinned' AND win_amount > 0 AND is_bomb = 0");
    $total_win = 0.0;
    $count = 0;
    
    while ($record = mysql_fetch_assoc($res)) {
        sql_query("UPDATE users SET seedbonus = seedbonus + " . sqlesc($record['win_amount']) . 
                " WHERE id = " . sqlesc($user_id));
        $total_win += $record['win_amount'];
        $count++;
        
        sql_query("UPDATE slot_machine SET settle_time = " . sqlesc(date('Y-m-d H:i:s')) . 
                ", status = 'settled' WHERE id = " . sqlesc($record['id']));
    }
    
    if ($count > 0) {
        $CURUSER['seedbonus'] = $CURUSER['seedbonus'] + $total_win;
        $msg = "⚡ 已自动结算 " . $count . " 笔记录，获得 " . $total_win . " 魔力值！";
    } else {
        $msg = "无未结算记录";
    }
    
    return [
        'success' => true,
        'count' => $count,
        'total_win' => $total_win,
        'msg' => $msg,
        'balance' => $CURUSER['seedbonus']
    ];
}

// AJAX接口处理
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'msg' => '未知错误'];
    
    switch ($_POST['ajax_action']) {
        case 'spin':
            $use_free = isset($_POST['free']) ? (int)$_POST['free'] : 0;
            $response = spin_slot_machine($CURUSER, $slot_config, $use_free);
            break;
            
        case 'settle':
            $spin_id = isset($_POST['spin_id']) ? (int)$_POST['spin_id'] : 0;
            if ($spin_id > 0) {
                $response = settle_slot_win($spin_id);
            } else {
                $response['msg'] = '无效的记录ID';
            }
            break;
            
        case 'auto_settle':
            $response = auto_settle_spins($CURUSER['id']);
            break;
    }
    
    echo json_encode($response);
    exit;
}

// 初始化数据表
init_slot_table();

// 页面渲染
$daily_limit_info = check_daily_limit($CURUSER['id'], $slot_config['daily_limit'], $slot_config['free_spins']);
$current_balance = number_format($CURUSER['seedbonus'], 1);

// 计算免费按钮状态
$free_btn_disabled = $daily_limit_info['free_remaining'] <= 0;
$free_btn_opacity = $free_btn_disabled ? '0.5' : '1';
$free_btn_cursor = $free_btn_disabled ? 'not-allowed' : 'pointer';
$free_btn_bg = $free_btn_disabled ? 'linear-gradient(135deg, #95a5a6, #7f8c8d)' : 'linear-gradient(135deg, #2ecc71, #27ae60)';

stdhead("魔力值老虎机");
?>

<div class="main_column" style="max-width: 800px; margin: 0 auto; padding: 20px;">
    <h1 style="color: #e74c3c; border-bottom: 3px solid #c0392b; padding-bottom: 10px; text-align: center; font-size: 32px;">
        🎰 魔力值老虎机 🎰
    </h1>
    
    <!-- 消息提示框 -->
    <div id="messageBox" style="display: none; padding: 15px; margin: 20px 0; border-radius: 8px; text-align: center; font-size: 18px; font-weight: bold;"></div>
    
    <!-- 余额信息和统计 -->
    <div class="balance_info" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; margin: 20px 0; border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <h3 style="margin: 0 0 15px 0; text-align: center; font-size: 24px; text-shadow: 1px 1px 3px rgba(0,0,0,0.3);">
            💰 当前魔力值: <span id="currentBalance" style="color: #f1c40f; font-size: 36px;"><?php echo $current_balance; ?></span>
        </h3>
        <div style="display: flex; justify-content: space-around; flex-wrap: wrap; gap: 15px; margin-top: 15px;">
            <div style="text-align: center; flex: 1; min-width: 150px;">
                <div style="font-size: 14px; opacity: 0.9;">单次消耗</div>
                <div style="font-size: 20px; font-weight: bold;"><?php echo $slot_config['cost']; ?> 魔力值</div>
            </div>
            <div style="text-align: center; flex: 1; min-width: 150px;">
                <div style="font-size: 14px; opacity: 0.9;">今日免费次数</div>
                <div id="freeRemaining" style="font-size: 20px; font-weight: bold; color: <?php echo $daily_limit_info['free_remaining'] > 0 ? '#2ecc71' : '#e74c3c'; ?>">
                    <?php echo $daily_limit_info['free_remaining']; ?>/<?php echo $slot_config['free_spins']; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 老虎机游戏区域 -->
    <div class="slot_area" style="background-color: #2c3e50; padding: 30px; margin: 20px 0; border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.3); text-align: center; border: 4px solid #34495e;">
        <h2 style="color: #ecf0f1; margin-top: 0; margin-bottom: 30px; font-size: 28px;">🎮 游戏区域 🎮</h2>
        
        <!-- 老虎机显示区域 -->
        <div id="slotDisplay" style="display: flex; justify-content: center; gap: 10px; margin-bottom: 30px;">
            <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="reel" style="width: 100px; height: 100px; background: linear-gradient(145deg, #2c3e50, #34495e); 
                    border-radius: 10px; display: flex; align-items: center; justify-content: center; 
                    font-size: 48px; color: #7f8c8d; box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
                    border: 3px dashed #95a5a6; transition: all 0.3s;">
                ?
            </div>
            <?php endfor; ?>
        </div>
        
        <!-- 操作按钮 -->
        <div class="btn-container" style="display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;">
            <button id="paidSpinBtn" 
                    style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; border: none; 
                           padding: 15px 40px; border-radius: 50px; cursor: pointer; font-size: 20px; 
                           font-weight: bold; transition: all 0.3s; box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4);
                           min-width: 180px;"
                    data-free="0">
                🎰 旋转 (<?php echo $slot_config['cost']; ?>魔力值)
            </button>
            
            <button id="freeSpinBtn" 
                    style="background: <?php echo $free_btn_bg; ?>; color: white; border: none; 
                           padding: 15px 40px; border-radius: 50px; font-size: 20px; 
                           font-weight: bold; transition: all 0.3s; box-shadow: 0 4px 15px rgba(46, 204, 113, 0.4);
                           min-width: 180px; opacity: <?php echo $free_btn_opacity; ?>; 
                           cursor: <?php echo $free_btn_cursor; ?>;"
                    data-free="1" 
                    <?php echo $free_btn_disabled ? 'disabled' : ''; ?>
                    title="<?php echo $free_btn_disabled ? '今日免费次数已用完' : '点击使用免费旋转'; ?>">
                🎁 免费旋转
            </button>
            
            <button id="settleBtn" 
                    style="background: linear-gradient(135deg, #f39c12, #e67e22); color: white; border: none; 
                           padding: 15px 40px; border-radius: 50px; cursor: pointer; font-size: 20px; 
                           font-weight: bold; transition: all 0.3s; box-shadow: 0 4px 15px rgba(243, 156, 18, 0.4);
                           min-width: 180px; display: none;"
                    data-spin-id="0">
                💰 结算奖励
            </button>
            
            <button id="autoSettleBtn" 
                    style="background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; border: none; 
                           padding: 15px 40px; border-radius: 50px; cursor: pointer; font-size: 20px; 
                           font-weight: bold; transition: all 0.3s; box-shadow: 0 4px 15px rgba(155, 89, 182, 0.4);
                           min-width: 180px;">
                ⚡ 一键结算所有
            </button>
        </div>
    </div>
    
    <!-- 游戏规则和赔率 -->
    <div class="slot_rules" style="background-color: #ecf0f1; padding: 25px; margin: 20px 0; border-radius: 12px; border-left: 6px solid #3498db;">
        <h2 style="color: #2c3e50; margin-top: 0; font-size: 24px;">📖 游戏规则 & 赔率表</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px;">
            <?php foreach ($slot_config['symbols'] as $symbol): ?>
            <div style="background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: <?php echo $symbol[0] == $slot_config['jackpot_symbol'] ? '2px solid #f1c40f' : ($symbol[0] == $slot_config['bomb_symbol'] ? '2px solid #e74c3c' : 'none'); ?>">
                <div style="font-size: 32px; margin-bottom: 8px;"><?php echo $symbol[0]; ?></div>
                <div style="font-size: 18px; color: <?php echo $symbol[1] == 0 ? '#e74c3c' : '#e74c3c'; ?>; font-weight: bold;">
                    <?php echo $symbol[1] == 0 ? '💣 清空' : 'x' . $symbol[1] . '倍'; ?>
                </div>
                <div style="font-size: 14px; color: #7f8c8d; margin-top: 5px;">概率: <?php echo $symbol[2]; ?>%</div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div style="margin-top: 25px; padding: 20px; background: white; border-radius: 8px;">
            <h3 style="color: #2c3e50; margin-top: 0;">🎯 游戏说明</h3>
            <ul style="line-height: 1.8; color: #34495e; padding-left: 20px;">
                <li>三个相同符号中奖，奖励消耗魔力值 × 符号倍数 × 2</li>
                <li>两个相同符号中奖，奖励消耗魔力值 × 符号倍数 × 0.5</li>
                <li>炸弹💣：出现炸弹会清空当次奖励</li>
                <li>每日有<?php echo $slot_config['free_spins']; ?>次免费旋转机会</li>
                <li>中奖后需要手动结算</li>
            </ul>
        </div>
    </div>
</div>

<style>
@keyframes slotSpin {
    0% { transform: rotateX(0deg) scale(0.9); opacity: 0.5; }
    50% { transform: rotateX(180deg) scale(1.1); opacity: 0.8; }
    100% { transform: rotateX(360deg) scale(1); opacity: 1; }
}

@keyframes jackpotFlash {
    0%, 100% { box-shadow: 0 0 20px #f1c40f; }
    50% { box-shadow: 0 0 40px #f1c40f, 0 0 60px #e67e22; }
}

@keyframes bombFlash {
    0%, 100% { box-shadow: 0 0 20px #e74c3c; }
    50% { box-shadow: 0 0 40px #e74c3c, 0 0 60px #c0392b; }
}

@keyframes winPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.15); }
}

@keyframes fadeIn {
    0% { opacity: 0; transform: translateY(10px); }
    100% { opacity: 1; transform: translateY(0); }
}

@keyframes fadeOut {
    0% { opacity: 1; transform: translateY(0); }
    100% { opacity: 0; transform: translateY(-10px); }
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.jackpot-animation { animation: jackpotFlash 0.5s ease-in-out 5; }
.bomb-animation { animation: bombFlash 0.3s ease-in-out 4; }
.win-animation { animation: winPulse 0.3s ease-in-out 2; }

.btn-loading {
    opacity: 0.8 !important;
    cursor: not-allowed !important;
    transform: scale(0.98) !important;
}

.btn-loading::after {
    content: "";
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,0.5);
    border-radius: 50%;
    border-top-color: white;
    margin-left: 10px;
    animation: spin 1s linear infinite;
}

.msg-success {
    background-color: #e8f5e9;
    border: 2px solid #28a745;
    color: #155724;
}

.msg-warning {
    background-color: #fff3cd;
    border: 2px solid #ffc107;
    color: #856404;
}

.msg-info {
    background-color: #e0f2fe;
    border: 2px solid #17a2b8;
    color: #0c5460;
}

.msg-error {
    background-color: #fef2f2;
    border: 2px solid #dc3545;
    color: #721c24;
}

.msg-bomb {
    background-color: #ffebee;
    border: 2px solid #f44336;
    color: #c62828;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 全局元素
    const paidSpinBtn = document.getElementById('paidSpinBtn');
    const freeSpinBtn = document.getElementById('freeSpinBtn');
    const settleBtn = document.getElementById('settleBtn');
    const autoSettleBtn = document.getElementById('autoSettleBtn');
    const slotDisplay = document.getElementById('slotDisplay');
    const reels = document.querySelectorAll('.reel');
    const messageBox = document.getElementById('messageBox');
    const currentBalanceEl = document.getElementById('currentBalance');
    const freeRemainingEl = document.getElementById('freeRemaining');
    
    // 更新免费按钮状态
    function updateFreeButtonState(freeRemaining, freeExhausted) {
        const freeBtn = document.getElementById('freeSpinBtn');
        
        if (freeExhausted || freeRemaining <= 0) {
            freeBtn.disabled = true;
            freeBtn.style.opacity = '0.5';
            freeBtn.style.cursor = 'not-allowed';
            freeBtn.style.background = 'linear-gradient(135deg, #95a5a6, #7f8c8d)';
            freeBtn.title = '今日免费次数已用完';
        } else {
            freeBtn.disabled = false;
            freeBtn.style.opacity = '1';
            freeBtn.style.cursor = 'pointer';
            freeBtn.style.background = 'linear-gradient(135deg, #2ecc71, #27ae60)';
            freeBtn.title = '点击使用免费旋转';
        }
        
        if (freeRemainingEl) {
            freeRemainingEl.textContent = freeRemaining + '/' + <?php echo $slot_config['free_spins']; ?>;
            freeRemainingEl.style.color = freeRemaining > 0 ? '#2ecc71' : '#e74c3c';
        }
    }
    
    // AJAX请求函数
    function ajaxRequest(action, data = {}, callback) {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        
        data.ajax_action = action;
        const formData = Object.keys(data).map(key => 
            encodeURIComponent(key) + '=' + encodeURIComponent(data[key])
        ).join('&');
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    callback(response);
                } catch (e) {
                    showMessage('请求失败：数据格式错误', 'error');
                    console.error('AJAX解析错误:', e);
                }
            } else {
                showMessage('请求失败：HTTP状态 ' + xhr.status, 'error');
                console.error('AJAX请求错误:', xhr.statusText);
            }
        };
        
        xhr.onerror = function() {
            showMessage('网络错误：无法连接服务器', 'error');
            console.error('AJAX网络错误');
        };
        
        xhr.send(formData);
    }
    
    // 显示消息提示
    function showMessage(msg, type = 'info') {
        messageBox.textContent = msg;
        messageBox.className = 'msg-' + type;
        messageBox.style.display = 'block';
        messageBox.style.animation = 'fadeIn 0.5s ease-in-out';
        
        setTimeout(() => {
            messageBox.style.animation = 'fadeOut 0.5s ease-in-out';
            setTimeout(() => {
                messageBox.style.display = 'none';
                messageBox.style.animation = '';
            }, 500);
        }, 3000);
    }
    
    // 老虎机动画
    function animateReels(callback) {
        reels.forEach(reel => {
            reel.style.animation = 'slotSpin 0.8s ease-out';
            reel.style.opacity = '0.5';
            reel.style.borderStyle = 'solid';
            reel.style.borderColor = '#1abc9c';
        });
        
        setTimeout(() => {
            reels.forEach(reel => {
                reel.style.animation = '';
                reel.style.opacity = '1';
            });
            callback();
        }, 800);
    }
    
    // 更新老虎机结果
    function updateReels(symbols, resultInfo) {
        reels.forEach((reel, index) => {
            reel.textContent = symbols[index];
            reel.style.backgroundColor = 'linear-gradient(145deg, #3498db, #2980b9)';
            reel.style.color = 'white';
            reel.style.borderStyle = 'solid';
            
            reel.classList.remove('jackpot-animation', 'bomb-animation', 'win-animation');
            
            if (resultInfo.is_bomb) {
                reel.style.borderColor = '#e74c3c';
                reel.classList.add('bomb-animation');
            } else if (resultInfo.is_jackpot) {
                reel.style.borderColor = '#f1c40f';
                reel.classList.add('jackpot-animation');
            } else if (resultInfo.win_amount > 0) {
                reel.style.borderColor = '#2ecc71';
                reel.classList.add('win-animation');
            } else {
                reel.style.borderColor = '#1abc9c';
            }
        });
    }
    
    // 重置老虎机状态
    function resetReels() {
        reels.forEach(reel => {
            reel.textContent = '?';
            reel.style.backgroundColor = 'linear-gradient(145deg, #2c3e50, #34495e)';
            reel.style.color = '#7f8c8d';
            reel.style.borderStyle = 'dashed';
            reel.style.borderColor = '#95a5a6';
            reel.classList.remove('jackpot-animation', 'bomb-animation', 'win-animation');
        });
        settleBtn.style.display = 'none';
        settleBtn.setAttribute('data-spin-id', '0');
    }
    
    // 旋转按钮点击事件
    [paidSpinBtn, freeSpinBtn].forEach(btn => {
        if (btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                if (this.id === 'freeSpinBtn' && this.disabled) {
                    showMessage('今日免费次数已用完！请使用魔力值旋转。', 'warning');
                    return;
                }
                
                if (this.classList.contains('btn-loading')) return;
                
                const isFree = this.dataset.free === '1';
                
                this.classList.add('btn-loading');
                paidSpinBtn.disabled = true;
                freeSpinBtn.disabled = true;
                
                animateReels(() => {
                    ajaxRequest('spin', { free: isFree ? 1 : 0 }, function(response) {
                        btn.classList.remove('btn-loading');
                        paidSpinBtn.disabled = false;
                        
                        if (response.success) {
                            currentBalanceEl.textContent = parseFloat(response.balance).toFixed(1);
                            
                            updateFreeButtonState(response.free_remaining, response.free_exhausted);
                            
                            updateReels(response.symbols, response);
                            
                            if (response.win_amount > 0 && !response.is_bomb) {
                                settleBtn.style.display = 'inline-block';
                                settleBtn.setAttribute('data-spin-id', response.spin_id);
                            } else {
                                setTimeout(resetReels, 3000);
                            }
                            
                            let msgType = 'info';
                            if (response.is_bomb) msgType = 'bomb';
                            else if (response.is_jackpot) msgType = 'success';
                            else if (response.win_amount > 0) msgType = 'success';
                            
                            showMessage(response.msg, msgType);
                            
                        } else {
                            resetReels();
                            showMessage(response.msg, 'error');
                            
                            updateFreeButtonState(
                                <?php echo $daily_limit_info['free_remaining']; ?>,
                                <?php echo $daily_limit_info['free_exhausted'] ? 'true' : 'false'; ?>
                            );
                        }
                    });
                });
            });
        }
    });
    
    // 结算按钮点击事件
    if (settleBtn) {
        settleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            const spinId = this.getAttribute('data-spin-id');
            if (!spinId || spinId === '0') return;
            
            this.classList.add('btn-loading');
            this.disabled = true;
            
            ajaxRequest('settle', { spin_id: spinId }, function(response) {
                settleBtn.classList.remove('btn-loading');
                settleBtn.disabled = false;
                
                if (response.success) {
                    currentBalanceEl.textContent = parseFloat(response.balance).toFixed(1);
                    showMessage(response.msg, response.win_amount > 0 ? 'success' : 'info');
                    
                    setTimeout(resetReels, 1500);
                } else {
                    showMessage(response.msg, 'error');
                }
            });
        });
    }
    
    // 自动结算按钮点击事件
    if (autoSettleBtn) {
        autoSettleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            if (this.classList.contains('btn-loading')) return;
            
            if (confirm('确定要结算所有未结算的奖励吗？')) {
                this.classList.add('btn-loading');
                
                ajaxRequest('auto_settle', {}, function(response) {
                    autoSettleBtn.classList.remove('btn-loading');
                    
                    if (response.success) {
                        currentBalanceEl.textContent = parseFloat(response.balance).toFixed(1);
                        showMessage(response.msg, 'success');
                    } else {
                        showMessage(response.msg, 'error');
                    }
                });
            }
        });
    }
    
    // 键盘快捷键
    document.addEventListener('keydown', function(e) {
        if (e.code === 'Space' && !e.target.matches('input, textarea, select')) {
            e.preventDefault();
            if (paidSpinBtn && !paidSpinBtn.disabled && !paidSpinBtn.classList.contains('btn-loading')) {
                paidSpinBtn.click();
            }
        }
        
        if (e.code === 'KeyF' && !e.target.matches('input, textarea, select')) {
            e.preventDefault();
            if (freeSpinBtn && !freeSpinBtn.disabled && !freeSpinBtn.classList.contains('btn-loading')) {
                freeSpinBtn.click();
            }
        }
    }, { capture: true });
    
    // 初始更新免费按钮状态
    updateFreeButtonState(
        <?php echo $daily_limit_info['free_remaining']; ?>,
        <?php echo $daily_limit_info['free_exhausted'] ? 'true' : 'false'; ?>
    );
});
</script>

<?php
stdfoot();