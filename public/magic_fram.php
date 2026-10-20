<?php
require_once('../include/bittorrent.php');
dbconn();
loggedinorreturn();
parked();

// 创建必要的数据库表
function create_farm_tables() {
    global $db;
    
    // 用户元数据表（存储农场相关数据）
    $table_sql = "
    CREATE TABLE IF NOT EXISTS user_meta (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        meta_key VARCHAR(255) NOT NULL,
        value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY user_meta_unique (user_id, meta_key),
        KEY user_id_idx (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    sql_query($table_sql) or sqlerr(__FILE__, __LINE__);
    
    // 农场日志表（记录所有操作）
    $log_sql = "
    CREATE TABLE IF NOT EXISTS farm_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(50) NOT NULL,
        item_type VARCHAR(20) NOT NULL,
        item_id INT NOT NULL,
        item_name VARCHAR(100) NOT NULL,
        points INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY user_id_idx (user_id),
        KEY created_at_idx (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    sql_query($log_sql) or sqlerr(__FILE__, __LINE__);
}

// 执行表创建
create_farm_tables();

// 定义文本常量
$lang = [
    'head_farm' => "的农场",
    'text_current_points' => "当前魔力值: ",
    'text_crops' => "农作物种植区",
    'text_animals' => "动物养殖区",
    'text_market' => "菜市场",
    'text_warehouse' => "仓库",
    'text_grow_time' => "成长时间",
    'text_price' => "价格",
    'btn_plant' => "种植",
    'btn_breed' => "养殖",
    'btn_harvest' => "收获",
    'btn_sell' => "出售",
    'text_harvested' => "已收获",
    'text_expire' => "过期时间",
    'text_market_price' => "市场价格",
    'text_no_items' => "暂无物品",
    'text_not_enough_points' => "魔力值不足",
    'text_already_planting' => "已在种植中",
    'text_already_breeding' => "已在养殖中",
    'text_harvest_success' => "收获成功: ",
    'text_plant_success' => "种植成功: ",
    'text_breed_success' => "养殖成功: ",
    'text_sell_success' => "出售成功: ",
    'text_expired' => "已过期",
    'text_one_key_harvest' => "一键收获",
    'text_time_left' => "剩余时间: ",
    'text_market_update' => "市场价格每24小时更新一次，波动范围±20%",
    'text_crop_expire' => "农作物有效期2天",
    'text_animal_expire' => "动物有效期5天"
];

// 农场物品定义（已添加 img 字段）
$crops = [
    ['id' => 1, 'name' => '小麦', 'grow_time' => 4 * 3600, 'price' => 500, 'expire' => 2 * 86400, 'img' => 'pic/farm/crop_wheat4.png'],
    ['id' => 2, 'name' => '玉米', 'grow_time' => 8 * 3600, 'price' => 1000, 'expire' => 2 * 86400, 'img' => 'pic/farm/crop_corn4.png'],
    ['id' => 3, 'name' => '花生', 'grow_time' => 12 * 3600, 'price' => 1500, 'expire' => 2 * 86400, 'img' => 'pic/farm/crop_peanut4.png'],
    ['id' => 4, 'name' => '土豆', 'grow_time' => 24 * 3600, 'price' => 2000, 'expire' => 2 * 86400, 'img' => 'pic/farm/crop_potato1.png']
];

$animals = [
    ['id' => 1, 'name' => '鸡', 'grow_time' => 24 * 3600, 'price' => 1000, 'expire' => 5 * 86400, 'img' => 'pic/farm/animal_chicken1.png'],
    ['id' => 2, 'name' => '猪', 'grow_time' => 48 * 3600, 'price' => 2000, 'expire' => 5 * 86400, 'img' => 'pic/farm/animal_pig1.png'],
    ['id' => 3, 'name' => '羊', 'grow_time' => 72 * 3600, 'price' => 5000, 'expire' => 5 * 86400, 'img' => 'pic/farm/animal_sheep1.png'],
    ['id' => 4, 'name' => '牛', 'grow_time' => 96 * 3600, 'price' => 10000, 'expire' => 5 * 86400, 'img' => 'pic/farm/animal_cow2.png']
];

// 辅助函数 - 获取用户元数据
function get_user_meta($user_id, $key, $default = '') {
    $res = sql_query("SELECT value FROM user_meta WHERE user_id = " . sqlesc($user_id) . " AND meta_key = " . sqlesc($key));
    if (mysql_num_rows($res) == 0) {
        return $default;
    }
    $row = mysql_fetch_assoc($res);
    $value = $row['value'];
    return is_numeric($value) ? $value : unserialize($value);
}

// 辅助函数 - 设置用户元数据
function set_user_meta($user_id, $key, $value) {
    $serialized = is_array($value) || is_object($value) ? serialize($value) : $value;
    $res = sql_query("SELECT id FROM user_meta WHERE user_id = " . sqlesc($user_id) . " AND meta_key = " . sqlesc($key));
    if (mysql_num_rows($res) > 0) {
        sql_query("UPDATE user_meta SET value = " . sqlesc($serialized) . ", updated_at = NOW() WHERE user_id = " . sqlesc($user_id) . " AND meta_key = " . sqlesc($key));
    } else {
        sql_query("INSERT INTO user_meta (user_id, meta_key, value) VALUES (" . sqlesc($user_id) . ", " . sqlesc($key) . ", " . sqlesc($serialized) . ")");
    }
}

// 记录农场操作日志
function log_farm_action($user_id, $action, $item_type, $item_id, $item_name, $points) {
    sql_query("INSERT INTO farm_logs 
              (user_id, action, item_type, item_id, item_name, points) 
              VALUES 
              (" . sqlesc($user_id) . ", " . sqlesc($action) . ", " . sqlesc($item_type) . ", 
               " . sqlesc($item_id) . ", " . sqlesc($item_name) . ", " . sqlesc($points) . ")");
}

// 市场价格更新（每天一次）
function update_market_prices($user_id) {
    global $crops, $animals;
    
    $last_update = get_user_meta($user_id, 'market_last_update', 0);
    if (TIMENOW - $last_update > 86400) {
        // 更新农作物价格
        foreach ($crops as $crop) {
            $min = $crop['price'] * 0.8;
            $max = $crop['price'] * 1.2;
            $new_price = round(rand($min, $max));
            set_user_meta($user_id, 'market_crop_' . $crop['id'], $new_price);
        }
        
        // 更新动物价格
        foreach ($animals as $animal) {
            $min = $animal['price'] * 0.8;
            $max = $animal['price'] * 1.2;
            $new_price = round(rand($min, $max));
            set_user_meta($user_id, 'market_animal_' . $animal['id'], $new_price);
        }
        
        set_user_meta($user_id, 'market_last_update', TIMENOW);
    }
}

// 获取市场价格
function get_market_price($user_id, $type, $id) {
    global $crops, $animals;
    
    $meta_key = 'market_' . $type . '_' . $id;
    $price = get_user_meta($user_id, $meta_key, 0);
    
    if (!$price) {
        // 初始价格
        if ($type == 'crop') {
            foreach ($crops as $crop) {
                if ($crop['id'] == $id) {
                    return $crop['price'];
                }
            }
        } else {
            foreach ($animals as $animal) {
                if ($animal['id'] == $id) {
                    return $animal['price'];
                }
            }
        }
    }
    
    return $price;
}

// 获取种植/养殖状态
function get_farm_status($user_id, $type, $id) {
    return get_user_meta($user_id, $type . '_status_' . $id, []);
}

// 设置种植/养殖状态
function set_farm_status($user_id, $type, $id, $data) {
    set_user_meta($user_id, $type . '_status_' . $id, $data);
}

// 获取仓库物品
function get_warehouse($user_id) {
    $warehouse = get_user_meta($user_id, 'farm_warehouse', []);
    
    // 过滤过期物品
    foreach ($warehouse as $key => $item) {
        if ($item['expire_time'] < TIMENOW) {
            unset($warehouse[$key]);
        }
    }
    
    // 保存过滤后的仓库数据
    set_user_meta($user_id, 'farm_warehouse', $warehouse);
    return $warehouse;
}

// 添加物品到仓库
function add_to_warehouse($user_id, $type, $id, $name, $expire) {
    $warehouse = get_warehouse($user_id);
    $item_key = $type . '_' . $id;
    
    $expire_time = TIMENOW + $expire;
    
    if (isset($warehouse[$item_key])) {
        $warehouse[$item_key]['count']++;
    } else {
        $warehouse[$item_key] = [
            'type' => $type,
            'id' => $id,
            'name' => $name,
            'count' => 1,
            'expire_time' => $expire_time
        ];
    }
    
    // 限制最多10条记录
    if (count($warehouse) > 10) {
        array_pop($warehouse);
    }
    
    set_user_meta($user_id, 'farm_warehouse', $warehouse);
    return true;
}

// 从仓库移除物品
function remove_from_warehouse($user_id, $key) {
    $warehouse = get_warehouse($user_id);
    
    if (isset($warehouse[$key])) {
        $warehouse[$key]['count']--;
        if ($warehouse[$key]['count'] <= 0) {
            unset($warehouse[$key]);
        }
        set_user_meta($user_id, 'farm_warehouse', $warehouse);
        return true;
    }
    
    return false;
}

// 处理动作
$action = $_GET['action'] ?? '';
$type = $_GET['type'] ?? '';
$id = intval($_GET['id'] ?? 0);
$key = $_GET['key'] ?? '';
$msg = '';
$user_id = $CURUSER['id'];

// 更新市场价格
update_market_prices($user_id);

// 种植农作物
if ($action == 'plant' && $type == 'crop' && $id > 0) {
    foreach ($crops as $crop) {
        if ($crop['id'] == $id) {
            if ($CURUSER['seedbonus'] >= $crop['price']) {
                $status = get_farm_status($user_id, 'crop', $id);
                if (empty($status) || $status['end_time'] < TIMENOW) {
                    // 扣除魔力值
                    sql_query("UPDATE users SET seedbonus = seedbonus - " . $crop['price'] . " WHERE id = " . $user_id);
                    
                    // 设置种植状态
                    set_farm_status($user_id, 'crop', $id, [
                        'start_time' => TIMENOW,
                        'end_time' => TIMENOW + $crop['grow_time']
                    ]);
                    
                    // 记录日志
                    log_farm_action($user_id, 'plant', 'crop', $id, $crop['name'], -$crop['price']);
                    
                    $msg = $lang['text_plant_success'] . $crop['name'];
                } else {
                    $msg = $lang['text_already_planting'] . $crop['name'];
                }
            } else {
                $msg = $lang['text_not_enough_points'];
            }
            break;
        }
    }
}

// 养殖动物
if ($action == 'breed' && $type == 'animal' && $id > 0) {
    foreach ($animals as $animal) {
        if ($animal['id'] == $id) {
            if ($CURUSER['seedbonus'] >= $animal['price']) {
                $status = get_farm_status($user_id, 'animal', $id);
                if (empty($status) || $status['end_time'] < TIMENOW) {
                    // 扣除魔力值
                    sql_query("UPDATE users SET seedbonus = seedbonus - " . $animal['price'] . " WHERE id = " . $user_id);
                    
                    // 设置养殖状态
                    set_farm_status($user_id, 'animal', $id, [
                        'start_time' => TIMENOW,
                        'end_time' => TIMENOW + $animal['grow_time']
                    ]);
                    
                    // 记录日志
                    log_farm_action($user_id, 'breed', 'animal', $id, $animal['name'], -$animal['price']);
                    
                    $msg = $lang['text_breed_success'] . $animal['name'];
                } else {
                    $msg = $lang['text_already_breeding'] . $animal['name'];
                }
            } else {
                $msg = $lang['text_not_enough_points'];
            }
            break;
        }
    }
}

// 收获
if ($action == 'harvest' && ($type == 'crop' || $type == 'animal') && $id > 0) {
    $items = $type == 'crop' ? $crops : $animals;
    foreach ($items as $item) {
        if ($item['id'] == $id) {
            $status = get_farm_status($user_id, $type, $id);
            if (!empty($status) && $status['end_time'] <= TIMENOW) {
                // 添加到仓库
                add_to_warehouse($user_id, $type, $id, $item['name'], $item['expire']);
                
                // 清除状态
                set_farm_status($user_id, $type, $id, []);
                
                // 记录日志
                log_farm_action($user_id, 'harvest', $type, $id, $item['name'], 0);
                
                $msg = $lang['text_harvest_success'] . $item['name'];
            }
            break;
        }
    }
}

// 一键收获
if ($action == 'harvest_all') {
    $harvested = [];
    
    // 收获所有农作物
    foreach ($crops as $crop) {
        $status = get_farm_status($user_id, 'crop', $crop['id']);
        if (!empty($status) && $status['end_time'] <= TIMENOW) {
            add_to_warehouse($user_id, 'crop', $crop['id'], $crop['name'], $crop['expire']);
            set_farm_status($user_id, 'crop', $crop['id'], []);
            $harvested[] = $crop['name'];
            log_farm_action($user_id, 'harvest', 'crop', $crop['id'], $crop['name'], 0);
        }
    }
    
    // 收获所有动物
    foreach ($animals as $animal) {
        $status = get_farm_status($user_id, 'animal', $animal['id']);
        if (!empty($status) && $status['end_time'] <= TIMENOW) {
            add_to_warehouse($user_id, 'animal', $animal['id'], $animal['name'], $animal['expire']);
            set_farm_status($user_id, 'animal', $animal['id'], []);
            $harvested[] = $animal['name'];
            log_farm_action($user_id, 'harvest', 'animal', $animal['id'], $animal['name'], 0);
        }
    }
    
    if (!empty($harvested)) {
        $msg = $lang['text_harvest_success'] . implode(', ', $harvested);
    } else {
        $msg = "没有可收获的物品";
    }
}

// 出售物品
if ($action == 'sell' && !empty($key)) {
    $warehouse = get_warehouse($user_id);
    
    if (isset($warehouse[$key])) {
        $item = $warehouse[$key];
        $price = get_market_price($user_id, $item['type'], $item['id']);
        
        // 添加魔力值
        sql_query("UPDATE users SET seedbonus = seedbonus + " . $price . " WHERE id = " . $user_id);
        
        // 从仓库移除
        remove_from_warehouse($user_id, $key);
        
        // 记录日志
        log_farm_action($user_id, 'sell', $item['type'], $item['id'], $item['name'], $price);
        
        $msg = $lang['text_sell_success'] . $item['name'] . "，获得 " . $price . " 魔力值";
    }
}

// 重新获取用户数据（更新魔力值）
$res = sql_query("SELECT seedbonus FROM users WHERE id = " . $user_id);
$user = mysql_fetch_assoc($res);
$CURUSER['seedbonus'] = $user['seedbonus'];

stdhead($CURUSER['username'] . $lang['head_farm']);
?>

<div class="farm-container">
    <div class="farm-header">
        <h1><?php echo $CURUSER['username'] . $lang['head_farm'] ?></h1>
        <div class="points-display">
            <?php echo $lang['text_current_points'] . number_format($CURUSER['seedbonus'], 1) ?>
        </div>
        <?php if ($msg): ?>
            <div class="message"><?php echo $msg ?></div>
        <?php endif; ?>
    </div>
    
    <!-- 一键收获按钮 -->
    <div class="one-key-harvest">
        <a href="?action=harvest_all" class="btn"><?php echo $lang['text_one_key_harvest'] ?></a>
    </div>
    
    <!-- 农作物种植区域（已添加图片渲染） -->
    <div class="farm-section">
        <h2><?php echo $lang['text_crops'] ?> <small><?php echo $lang['text_crop_expire'] ?></small></h2>
        <div class="items-grid">
            <?php foreach ($crops as $crop): ?>
                <?php $status = get_farm_status($user_id, 'crop', $crop['id']); ?>
                <div class="farm-item">
                    <!-- 新增图片标签：读取 $crop['img'] 字段 -->
                    <img 
                        src="<?php echo htmlspecialchars($crop['img']) ?>" 
                        alt="<?php echo htmlspecialchars($crop['name']) ?>" 
                        class="item-img" 
                        referrerpolicy="no-referrer" 
                        crossorigin="anonymous" 
                        onerror="this.src='https://picsum.photos/80/80'; this.alt='<?php echo htmlspecialchars($crop['name']) ?>';"
                    >
                    <h3><?php echo htmlspecialchars($crop['name']) ?></h3>
                    <div class="item-info">
                        <p><?php echo $lang['text_grow_time'] ?>: <?php echo $crop['grow_time'] / 3600 ?> 小时</p>
                        <p><?php echo $lang['text_price'] ?>: <?php echo $crop['price'] ?></p>
                    </div>
                    <div class="item-actions">
                        <?php if (!empty($status) && $status['end_time'] > TIMENOW): ?>
                            <p class="growing-status">
                                <?php 
                                $time_left = $status['end_time'] - TIMENOW;
                                $hours = floor($time_left / 3600);
                                $minutes = floor(($time_left % 3600) / 60);
                                echo $lang['text_time_left'] . $hours . "小时" . $minutes . "分钟";
                                ?>
                            </p>
                        <?php elseif (!empty($status) && $status['end_time'] <= TIMENOW): ?>
                            <a href="?action=harvest&type=crop&id=<?php echo $crop['id'] ?>" class="btn harvest-btn">
                                <?php echo $lang['btn_harvest'] ?>
                            </a>
                        <?php else: ?>
                            <a href="?action=plant&type=crop&id=<?php echo $crop['id'] ?>" class="btn plant-btn">
                                <?php echo $lang['btn_plant'] ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- 动物养殖区域（已添加图片渲染） -->
    <div class="farm-section">
        <h2><?php echo $lang['text_animals'] ?> <small><?php echo $lang['text_animal_expire'] ?></small></h2>
        <div class="items-grid">
            <?php foreach ($animals as $animal): ?>
                <?php $status = get_farm_status($user_id, 'animal', $animal['id']); ?>
                <div class="farm-item">
                    <!-- 新增图片标签：读取 $animal['img'] 字段 -->
                    <img 
                        src="<?php echo htmlspecialchars($animal['img']) ?>" 
                        alt="<?php echo htmlspecialchars($animal['name']) ?>" 
                        class="item-img" 
                        referrerpolicy="no-referrer" 
                        crossorigin="anonymous" 
                        onerror="this.src='https://picsum.photos/80/80'; this.alt='<?php echo htmlspecialchars($animal['name']) ?>';"
                    >
                    <h3><?php echo htmlspecialchars($animal['name']) ?></h3>
                    <div class="item-info">
                        <p><?php echo $lang['text_grow_time'] ?>: <?php echo $animal['grow_time'] / 3600 ?> 小时</p>
                        <p><?php echo $lang['text_price'] ?>: <?php echo $animal['price'] ?></p>
                    </div>
                    <div class="item-actions">
                        <?php if (!empty($status) && $status['end_time'] > TIMENOW): ?>
                            <p class="growing-status">
                                <?php 
                                $time_left = $status['end_time'] - TIMENOW;
                                $hours = floor($time_left / 3600);
                                $minutes = floor(($time_left % 3600) / 60);
                                echo $lang['text_time_left'] . $hours . "小时" . $minutes . "分钟";
                                ?>
                            </p>
                        <?php elseif (!empty($status) && $status['end_time'] <= TIMENOW): ?>
                            <a href="?action=harvest&type=animal&id=<?php echo $animal['id'] ?>" class="btn harvest-btn">
                                <?php echo $lang['btn_harvest'] ?>
                            </a>
                        <?php else: ?>
                            <a href="?action=breed&type=animal&id=<?php echo $animal['id'] ?>" class="btn breed-btn">
                                <?php echo $lang['btn_breed'] ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- 仓库（可选添加图片，已优化） -->
    <div class="farm-section">
        <h2><?php echo $lang['text_warehouse'] ?></h2>
        <div class="warehouse-list">
            <?php $warehouse = get_warehouse($user_id); ?>
            <?php if (empty($warehouse)): ?>
                <p><?php echo $lang['text_no_items'] ?></p>
            <?php else: ?>
                <table class="warehouse-table">
                    <tr>
                        <th>物品图片</th>
                        <th>物品名称</th>
                        <th>数量</th>
                        <th><?php echo $lang['text_expire'] ?></th>
                        <th>操作</th>
                    </tr>
                    <?php foreach ($warehouse as $item_key => $item): ?>
                        <?php
                        // 匹配仓库物品图片
                        $img_path = 'https://picsum.photos/30/30'; // 默认占位图
                        if ($item['type'] == 'crop') {
                            foreach ($crops as $crop) {
                                if ($crop['id'] == $item['id']) {
                                    $img_path = $crop['img'];
                                    break;
                                }
                            }
                        } else {
                            foreach ($animals as $animal) {
                                if ($animal['id'] == $item['id']) {
                                    $img_path = $animal['img'];
                                    break;
                                }
                            }
                        }
                        ?>
                        <tr>
                            <td>
                                <img 
                                    src="<?php echo htmlspecialchars($img_path) ?>" 
                                    alt="<?php echo htmlspecialchars($item['name']) ?>" 
                                    class="warehouse-item-img"
                                    onerror="this.src='https://picsum.photos/30/30';"
                                >
                            </td>
                            <td><?php echo htmlspecialchars($item['name']) ?></td>
                            <td><?php echo $item['count'] ?></td>
                            <td>
                                <?php 
                                $expire_left = $item['expire_time'] - TIMENOW;
                                if ($expire_left <= 0) {
                                    echo $lang['text_expired'];
                                } else {
                                    $days = floor($expire_left / 86400);
                                    $hours = floor(($expire_left % 86400) / 3600);
                                    echo $days . "天" . $hours . "小时";
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($expire_left > 0): ?>
                                    <a href="?action=sell&key=<?php echo $item_key ?>" class="btn sell-btn">
                                        <?php echo $lang['btn_sell'] ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 菜市场（可选添加图片，已优化） -->
    <div class="farm-section">
        <h2><?php echo $lang['text_market'] ?></h2>
        <p class="market-note"><?php echo $lang['text_market_update'] ?></p>
        <div class="market-grid">
            <div class="market-category">
                <h3><?php echo $lang['text_crops'] ?></h3>
                <table class="market-table">
                    <tr>
                        <th>物品图片</th>
                        <th>物品名称</th>
                        <th><?php echo $lang['text_market_price'] ?></th>
                    </tr>
                    <?php foreach ($crops as $crop): ?>
                        <tr>
                            <td>
                                <img 
                                    src="<?php echo htmlspecialchars($crop['img']) ?>" 
                                    alt="<?php echo htmlspecialchars($crop['name']) ?>" 
                                    class="market-item-img"
                                    onerror="this.src='https://picsum.photos/30/30';"
                                >
                            </td>
                            <td><?php echo htmlspecialchars($crop['name']) ?></td>
                            <td><?php echo get_market_price($user_id, 'crop', $crop['id']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            
            <div class="market-category">
                <h3><?php echo $lang['text_animals'] ?></h3>
                <table class="market-table">
                    <tr>
                        <th>物品图片</th>
                        <th>物品名称</th>
                        <th><?php echo $lang['text_market_price'] ?></th>
                    </tr>
                    <?php foreach ($animals as $animal): ?>
                        <tr>
                            <td>
                                <img 
                                    src="<?php echo htmlspecialchars($animal['img']) ?>" 
                                    alt="<?php echo htmlspecialchars($animal['name']) ?>" 
                                    class="market-item-img"
                                    onerror="this.src='https://picsum.photos/30/30';"
                                >
                            </td>
                            <td><?php echo htmlspecialchars($animal['name']) ?></td>
                            <td><?php echo get_market_price($user_id, 'animal', $animal['id']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.farm-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: Arial, sans-serif;
}

.farm-header {
    text-align: center;
    margin-bottom: 30px;
}

.points-display {
    font-size: 1.2em;
    margin: 10px 0;
    color: #2c3e50;
    font-weight: bold;
}

.message {
    color: #27ae60;
    margin: 10px 0;
    padding: 10px;
    background-color: #ecf0f1;
    border-radius: 4px;
}

.one-key-harvest {
    text-align: center;
    margin-bottom: 20px;
}

.farm-section {
    margin-bottom: 40px;
    padding: 20px;
    background-color: #f9f9f9;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.farm-section h2 {
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
}

.items-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.farm-item {
    background-color: white;
    padding: 15px;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
    transition: transform 0.3s;
}

.farm-item:hover {
    transform: translateY(-5px);
}

/* 种植/养殖区物品图片样式 */
.item-img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 4px;
    margin-bottom: 10px;
}

.item-info {
    margin: 15px 0;
    text-align: left;
    color: #34495e;
}

.item-actions {
    margin-top: 20px;
}

.btn {
    display: inline-block;
    padding: 8px 16px;
    background-color: #3498db;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.3s;
}

.btn:hover {
    background-color: #2980b9;
}

.plant-btn {
    background-color: #27ae60;
}

.plant-btn:hover {
    background-color: #219653;
}

.breed-btn {
    background-color: #e67e22;
}

.breed-btn:hover {
    background-color: #d35400;
}

.harvest-btn {
    background-color: #f1c40f;
    color: #333;
}

.harvest-btn:hover {
    background-color: #f39c12;
}

.sell-btn {
    background-color: #e74c3c;
}

.sell-btn:hover {
    background-color: #c0392b;
}

.growing-status {
    color: #7f8c8d;
    padding: 8px;
    background-color: #ecf0f1;
    border-radius: 4px;
    margin: 0;
}

.warehouse-table, .market-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.warehouse-table th, .warehouse-table td,
.market-table th, .market-table td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: left;
}

.warehouse-table th, .market-table th {
    background-color: #f2f2f2;
    color: #2c3e50;
}

/* 仓库物品图片样式 */
.warehouse-item-img {
    width: 30px;
    height: 30px;
    object-fit: cover;
    vertical-align: middle;
    margin-right: 8px;
}

/* 菜市场物品图片样式 */
.market-item-img {
    width: 30px;
    height: 30px;
    object-fit: cover;
    vertical-align: middle;
    margin-right: 8px;
}

.market-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.market-category {
    background-color: white;
    padding: 15px;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.market-note {
    margin-top: 5px;
    color: #7f8c8d;
    font-size: 0.9em;
    font-style: italic;
}

@media (max-width: 768px) {
    .market-grid {
        grid-template-columns: 1fr;
    }
    
    .items-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 480px) {
    .items-grid {
        grid-template-columns: 1fr;
    }
    
    .warehouse-table, .market-table {
        font-size: 0.9em;
    }
    
    .btn {
        padding: 6px 12px;
        font-size: 13px;
    }
}
</style>

<?php stdfoot(); ?>