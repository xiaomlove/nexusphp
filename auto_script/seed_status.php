<?php

require "/www/wwwroot/qingwa.pro/include/bittorrent.php";
dbconn(true);

$sql = "SELECT id FROM users";
$res=sql_query($sql);
$max = 0;
//平均保种体积
$avg_size = 0;
//平均有效保种体积
$avg_valid_g = 0;
//平均实际保种体积
$avg_A = 0;
//总用户数量
$user_num = 0;
//零保种用户
$zero_users = 0;

/*
            $result = compact(
                'base_bonus', 'seed_points','seed_bonus', 'A', 'B', 'count', 'size', 'last_action',
                'buff_bonus', 'valid_g', 'all_g'
*/

while ($row=mysql_fetch_array($res)) {
    $result = calculate_seed_bonus($row['id']);
    $avg_size += $result['size'];
    $avg_A += $result['A'];
    $avg_valid_g += $result['valid_g'];
    $user_num ++;
    if ($result['size'] < 1) {
        $zero_users++;
    }
}
$valid_users = $user_num - $zero_users;

$avg_size_without_zero_users = $avg_size * 1.0 / $valid_users / 1024.0 / 1024.0 / 1024.0;
$avg_size = $avg_size * 1.0 / $user_num / 1024.0 / 1024.0 / 1024.0;
$avg_A_without_zero_users = $avg_A * 1.0 / $valid_users;
$avg_A = $avg_A * 1.0 / $user_num;
$avg_valid_g_without_zero_users = $avg_valid_g * 1.0 / $valid_users;
$avg_valid_g = $avg_valid_g * 1.0 / $user_num;

print json_encode(compact('user_num', 'zero_users', 'valid_users', 'avg_size', 'avg_size_without_zero_users',
    'avg_A', 'avg_A_without_zero_users', 'avg_valid_g', 'avg_valid_g_without_zero_users'), JSON_PRETTY_PRINT);
print "\nuser_num：总用户数量\nzero_users：未保种用户数量\nvalid_users：有保种用户数量\navg_size：平均保种体积\navg_size_without_zero_users：去除未保种用户后的保种体积\n";
print "avg_valid_g：有效保种体积\navg_valid_g_without_zero_users：去除未保种用户后的保种体积\navg_A：平均A值\navg_A_without_zero_users：去除未保种用户后的平均A值";

?>