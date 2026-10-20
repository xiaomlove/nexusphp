<?php
require_once("../include/bittorrent.php");
dbconn(true);
loggedinorreturn();
// ini_set('display_errors', 1);


// 检查用户权限 - 只有管理员可以访问
if (get_user_class() < UC_SYSOP) {
    stderr("访问被拒绝", "只有管理员才能访问此页面");
    stdfoot();
    exit;
}

// 处理AJAX请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = array();
    header('Content-Type: application/json; charset=utf-8');

    switch ($_POST['action']) {
        case 'search_user':
            $search_term = trim($_POST['search_term']);
            if (empty($search_term)) {
                $response['success'] = false;
                $response['message'] = "请输入用户名或UID";
                echo json_encode($response);
                exit;
            }

            // 判断是UID还是用户名搜索
            if (is_numeric($search_term)) {
                // UID搜索
                $user_sql = "SELECT id, username, class, title FROM users WHERE id = " . sqlesc($search_term);
            } else {
                // 用户名搜索
                $user_sql = "SELECT id, username, class, title FROM users WHERE username LIKE " . sqlesc('%' . $search_term . '%') . " LIMIT 10";
            }

            $user_res = sql_query($user_sql);
            $users = array();
            while ($user_row = mysql_fetch_assoc($user_res)) {
                // 获取用户的考核信息
                $assessment_sql = "SELECT uaa.assessment_type, ar.assessment_name, ar.config_name 
                                 FROM user_assessment_assignments uaa 
                                 LEFT JOIN assessment_requirements ar ON uaa.requirement_id = ar.id 
                                 WHERE uaa.uid = " . sqlesc($user_row['id']) . " LIMIT 1";
                $assessment_res = sql_query($assessment_sql);
                $current_assessment = null;
                if ($assessment_row = mysql_fetch_assoc($assessment_res)) {
                    $current_assessment = $assessment_row;
                }

                $users[] = array(
                    'id' => $user_row['id'],
                    'username' => $user_row['username'],
                    'class' => $user_row['class'],
                    'class_text' => get_user_class_name($user_row['class']),
                    'title' => $user_row['title'],
                    'current_assessment' => $current_assessment
                );
            }

            $response['success'] = true;
            $response['users'] = $users;
            echo json_encode($response);
            exit;

        case 'get_uploader_users':
            // 获取所有Uploader及以上身份的用户（class >= 12）
            $user_sql = "SELECT id, username, class, title FROM users WHERE class >= 12 ORDER BY class DESC, username ASC LIMIT 50";
            $user_res = sql_query($user_sql);
            $users = array();
            while ($user_row = mysql_fetch_assoc($user_res)) {
                // 获取用户的考核信息
                $assessment_sql = "SELECT uaa.assessment_type, ar.assessment_name, ar.config_name 
                                 FROM user_assessment_assignments uaa 
                                 LEFT JOIN assessment_requirements ar ON uaa.requirement_id = ar.id 
                                 WHERE uaa.uid = " . sqlesc($user_row['id']) . " LIMIT 1";
                $assessment_res = sql_query($assessment_sql);
                $current_assessment = null;
                if ($assessment_row = mysql_fetch_assoc($assessment_res)) {
                    $current_assessment = $assessment_row;
                }

                $users[] = array(
                    'id' => $user_row['id'],
                    'username' => $user_row['username'],
                    'class' => $user_row['class'],
                    'class_text' => get_user_class_name($user_row['class']),
                    'title' => $user_row['title'],
                    'current_assessment' => $current_assessment
                );
            }

            $response['success'] = true;
            $response['users'] = $users;
            echo json_encode($response);
            exit;

        case 'get_user_assessments':
            $uid = (int)$_POST['uid'];
            if ($uid <= 0) {
                $response['success'] = false;
                $response['message'] = "无效的用户ID";
                echo json_encode($response);
                exit;
            }

            // 获取用户当前信息
            $user_sql = "SELECT id, username, class, title FROM users WHERE id = " . sqlesc($uid);
            $user_res = sql_query($user_sql);
            $user = mysql_fetch_assoc($user_res);

            if (!$user) {
                $response['success'] = false;
                $response['message'] = "用户不存在";
                echo json_encode($response);
                exit;
            }

            // 获取用户当前的考核分配
            $assignment_sql = "SELECT uaa.*, ar.assessment_name, ar.config_name 
                             FROM user_assessment_assignments uaa 
                             LEFT JOIN assessment_requirements ar ON uaa.requirement_id = ar.id 
                             WHERE uaa.uid = " . sqlesc($uid);
            $assignment_res = sql_query($assignment_sql);
            $assignments = array();
            while ($assignment_row = mysql_fetch_assoc($assignment_res)) {
                $assignments[] = $assignment_row;
            }

            $response['success'] = true;
            $response['user'] = $user;
            $response['assignments'] = $assignments;
            echo json_encode($response);
            exit;

        case 'add_assessment':
            $uid = (int)$_POST['uid'];
            $requirement_id = (int)$_POST['requirement_id'];

            if ($uid <= 0 || $requirement_id <= 0) {
                $response['success'] = false;
                $response['message'] = "参数错误";
                echo json_encode($response);
                exit;
            }

            // 检查用户是否已有任何考核分配
            $check_sql = "SELECT id FROM user_assessment_assignments WHERE uid = " . sqlesc($uid);
            $check_res = sql_query($check_sql);
            if (mysql_num_rows($check_res) > 0) {
                $response['success'] = false;
                $response['message'] = "该用户已有考核分配，一个用户最多只能被分配一种考核";
                echo json_encode($response);
                exit;
            }

            // 获取考核配置信息
            $requirement_sql = "SELECT assessment_type, assessment_name FROM assessment_requirements WHERE id = " . sqlesc($requirement_id);
            $requirement_res = sql_query($requirement_sql);
            $requirement = mysql_fetch_assoc($requirement_res);

            if (!$requirement) {
                $response['success'] = false;
                $response['message'] = "考核配置不存在";
                echo json_encode($response);
                exit;
            }

            // 获取用户信息
            $user_sql = "SELECT username, class, title FROM users WHERE id = " . sqlesc($uid);
            $user_res = sql_query($user_sql);
            $user = mysql_fetch_assoc($user_res);

            // 添加考核分配
            $insert_sql = "INSERT INTO user_assessment_assignments (uid, user_class, user_title, assessment_type, requirement_id) VALUES (" .
                sqlesc($uid) . ", " . sqlesc($user['class']) . ", " . sqlesc($user['title']) . ", " . sqlesc($requirement['assessment_type']) . ", " . sqlesc($requirement_id) . ")";
            sql_query($insert_sql);

            $response['success'] = true;
            $response['message'] = "考核分配添加成功";
            echo json_encode($response);
            exit;

        case 'remove_assignment':
            $assignment_id = (int)$_POST['assignment_id'];

            if ($assignment_id <= 0) {
                $response['success'] = false;
                $response['message'] = "参数错误";
                echo json_encode($response);
                exit;
            }

            $delete_sql = "DELETE FROM user_assessment_assignments WHERE id = " . sqlesc($assignment_id);
            sql_query($delete_sql);

            $response['success'] = true;
            $response['message'] = "考核分配移除成功";
            echo json_encode($response);
            exit;

        case 'update_requirement':
            $requirement_id = (int)$_POST['requirement_id'];
            $field = trim($_POST['field']);
            $value = $_POST['value'];

            if ($requirement_id <= 0 || empty($field)) {
                $response['success'] = false;
                $response['message'] = "参数错误";
                echo json_encode($response);
                exit;
            }

            // 验证字段名
            $allowed_fields = array(
                'config_name',
                'base_salary',
                'interval_seconds',
                'publish_quantity_req',
                'min_publish_quantity_req',
                'publish_volume_req',
                'min_publish_volume_req',
                'publish_bonus_amount',
                'publish_bonus_qty_ratio',
                'publish_bonus_vol_ratio',
                'publish_max_bonus',
                'seed_quantity_req',
                'min_seed_quantity_req',
                'seed_volume_req',
                'min_seed_volume_req',
                'seed_bonus_amount',
                'seed_bonus_qty_ratio',
                'seed_bonus_vol_ratio',
                'seed_max_bonus',
                'audit_total_req',
                'min_audit_total_req',
                'audit_pass_req',
                'min_audit_pass_req',
                'audit_bonus_amount',
                'audit_bonus_total_ratio',
                'audit_bonus_pass_ratio',
                'audit_max_bonus'
            );

            if (!in_array($field, $allowed_fields)) {
                $response['success'] = false;
                $response['message'] = "无效的字段名";
                echo json_encode($response);
                exit;
            }

            $requirement_res = sql_query("SELECT * FROM assessment_requirements WHERE id = " . sqlesc($requirement_id) . " LIMIT 1");
            $requirement = mysql_fetch_assoc($requirement_res);
            if (!$requirement) {
                $response['success'] = false;
                $response['message'] = "考核配置不存在";
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($field === 'config_name') {
                $value = trim((string)$value);
                if ($value === '' || mb_strlen($value, 'UTF-8') > 50) {
                    $response['success'] = false;
                    $response['message'] = "配置名称长度应为1至50个字符";
                    echo json_encode($response, JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $duplicate_res = sql_query(
                    "SELECT id FROM assessment_requirements WHERE assessment_type = " . sqlesc($requirement['assessment_type']) .
                    " AND config_name = " . sqlesc($value) . " AND id != " . sqlesc($requirement_id) . " LIMIT 1"
                );
                if (mysql_num_rows($duplicate_res) > 0) {
                    $response['success'] = false;
                    $response['message'] = "同一岗位下已存在该配置名称";
                    echo json_encode($response, JSON_UNESCAPED_UNICODE);
                    exit;
                }
            } else {
                if (!is_numeric($value) || (float)$value < 0) {
                    $response['success'] = false;
                    $response['message'] = "配置值必须是大于或等于0的数字";
                    echo json_encode($response, JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $ratio_fields = [
                    'publish_bonus_qty_ratio', 'publish_bonus_vol_ratio',
                    'seed_bonus_qty_ratio', 'seed_bonus_vol_ratio',
                    'audit_bonus_total_ratio', 'audit_bonus_pass_ratio',
                ];
                if (in_array($field, $ratio_fields, true) && (float)$value <= 0) {
                    $response['success'] = false;
                    $response['message'] = "奖励比例必须大于0，避免工资计算除以零";
                    echo json_encode($response, JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $decimal_fields = [
                    'publish_volume_req', 'min_publish_volume_req', 'publish_bonus_vol_ratio',
                    'seed_volume_req', 'min_seed_volume_req', 'seed_bonus_vol_ratio',
                ];
                $value = in_array($field, $decimal_fields, true) ? (float)$value : (int)$value;
            }

            $candidate = $requirement;
            $candidate[$field] = $value;
            $requirement_pairs = [
                ['min_publish_quantity_req', 'publish_quantity_req', '最低发布数量不能高于发布奖励要求'],
                ['min_publish_volume_req', 'publish_volume_req', '最低发布体积不能高于发布奖励要求'],
                ['min_seed_quantity_req', 'seed_quantity_req', '最低保种数量不能高于保种奖励要求'],
                ['min_seed_volume_req', 'seed_volume_req', '最低保种体积不能高于保种奖励要求'],
                ['min_audit_total_req', 'audit_total_req', '最低操作总数不能高于操作奖励要求'],
                ['min_audit_pass_req', 'audit_pass_req', '最低通过审核数不能高于审核奖励要求'],
            ];
            foreach ($requirement_pairs as [$minimum_field, $target_field, $message]) {
                if ((float)$candidate[$minimum_field] > (float)$candidate[$target_field]) {
                    $response['success'] = false;
                    $response['message'] = $message;
                    echo json_encode($response, JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            $update_sql = "UPDATE assessment_requirements SET " . $field . " = " . sqlesc($value) . " WHERE id = " . sqlesc($requirement_id);
            sql_query($update_sql);

            $response['success'] = true;
            $response['message'] = "配置更新成功";
            echo json_encode($response);
            exit;

        case 'add_requirement_config':
            $assessment_type = (int)$_POST['assessment_type'];
            $config_name = trim($_POST['config_name']);

            if ($assessment_type < 1 || $assessment_type > 4 || empty($config_name)) {
                $response['success'] = false;
                $response['message'] = "参数错误";
                echo json_encode($response);
                exit;
            }

            if (mb_strlen($config_name, 'UTF-8') > 50) {
                $response['success'] = false;
                $response['message'] = "配置名称不能超过50个字符";
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 检查配置名称是否已存在
            $check_sql = "SELECT id FROM assessment_requirements WHERE assessment_type = " . sqlesc($assessment_type) . " AND config_name = " . sqlesc($config_name);
            $check_res = sql_query($check_sql);
            if (mysql_num_rows($check_res) > 0) {
                $response['success'] = false;
                $response['message'] = "该配置名称已存在";
                echo json_encode($response);
                exit;
            }

            // 获取该类型的默认配置作为模板
            $template_sql = "SELECT * FROM assessment_requirements WHERE assessment_type = " . sqlesc($assessment_type) . " LIMIT 1";
            $template_res = sql_query($template_sql);
            $template = mysql_fetch_assoc($template_res);

            if (!$template) {
                $response['success'] = false;
                $response['message'] = "未找到该类型的默认配置";
                echo json_encode($response);
                exit;
            }

            // 插入新配置
            $insert_sql = "INSERT INTO assessment_requirements (
                assessment_type, assessment_name, config_name, base_salary, interval_seconds,
                publish_quantity_req, min_publish_quantity_req, publish_volume_req, min_publish_volume_req,
                publish_bonus_amount, publish_bonus_qty_ratio, publish_bonus_vol_ratio, publish_max_bonus,
                seed_quantity_req, min_seed_quantity_req, seed_volume_req, min_seed_volume_req,
                seed_bonus_amount, seed_bonus_qty_ratio, seed_bonus_vol_ratio, seed_max_bonus,
                audit_total_req, min_audit_total_req, audit_pass_req, min_audit_pass_req,
                audit_bonus_amount, audit_bonus_total_ratio, audit_bonus_pass_ratio, audit_max_bonus
            ) VALUES (
                " . sqlesc($assessment_type) . ", " . sqlesc($template['assessment_name']) . ", " . sqlesc($config_name) . ", " . sqlesc($template['base_salary']) . ", " . sqlesc($template['interval_seconds']) . ",
                " . sqlesc($template['publish_quantity_req']) . ", " . sqlesc($template['min_publish_quantity_req']) . ", " . sqlesc($template['publish_volume_req']) . ", " . sqlesc($template['min_publish_volume_req']) . ",
                " . sqlesc($template['publish_bonus_amount']) . ", " . sqlesc($template['publish_bonus_qty_ratio']) . ", " . sqlesc($template['publish_bonus_vol_ratio']) . ", " . sqlesc($template['publish_max_bonus']) . ",
                " . sqlesc($template['seed_quantity_req']) . ", " . sqlesc($template['min_seed_quantity_req']) . ", " . sqlesc($template['seed_volume_req']) . ", " . sqlesc($template['min_seed_volume_req']) . ",
                " . sqlesc($template['seed_bonus_amount']) . ", " . sqlesc($template['seed_bonus_qty_ratio']) . ", " . sqlesc($template['seed_bonus_vol_ratio']) . ", " . sqlesc($template['seed_max_bonus']) . ",
                " . sqlesc($template['audit_total_req']) . ", " . sqlesc($template['min_audit_total_req']) . ", " . sqlesc($template['audit_pass_req']) . ", " . sqlesc($template['min_audit_pass_req']) . ",
                " . sqlesc($template['audit_bonus_amount']) . ", " . sqlesc($template['audit_bonus_total_ratio']) . ", " . sqlesc($template['audit_bonus_pass_ratio']) . ", " . sqlesc($template['audit_max_bonus']) . "
            )";

            sql_query($insert_sql);
            $new_id = mysql_insert_id();

            $response['success'] = true;
            $response['message'] = "配置添加成功";
            $response['new_id'] = $new_id;
            echo json_encode($response);
            exit;

        case 'delete_requirement_config':
            $requirement_id = (int)$_POST['requirement_id'];

            if ($requirement_id <= 0) {
                $response['success'] = false;
                $response['message'] = "参数错误";
                echo json_encode($response);
                exit;
            }

            // 检查是否有用户正在使用此配置
            $check_sql = "SELECT COUNT(*) as count FROM user_assessment_assignments WHERE requirement_id = " . sqlesc($requirement_id);
            $check_res = sql_query($check_sql);
            $check = mysql_fetch_assoc($check_res);

            if ($check['count'] > 0) {
                $response['success'] = false;
                $response['message'] = "该配置正在被用户使用，无法删除";
                echo json_encode($response);
                exit;
            }

            // 检查是否为默认配置
            $config_sql = "SELECT config_name FROM assessment_requirements WHERE id = " . sqlesc($requirement_id);
            $config_res = sql_query($config_sql);
            $config = mysql_fetch_assoc($config_res);

            if ($config && $config['config_name'] == '默认配置') {
                $response['success'] = false;
                $response['message'] = "默认配置无法删除";
                echo json_encode($response);
                exit;
            }

            $delete_sql = "DELETE FROM assessment_requirements WHERE id = " . sqlesc($requirement_id);
            sql_query($delete_sql);

            $response['success'] = true;
            $response['message'] = "配置删除成功";
            echo json_encode($response);
            exit;
    }
}

// 获取所有考核类型配置
$requirements_sql = "SELECT * FROM assessment_requirements ORDER BY assessment_type, config_name";
$requirements_res = sql_query($requirements_sql);
$requirements = array();
while ($req_row = mysql_fetch_assoc($requirements_res)) {
    $requirements[] = $req_row;
}

$assignment_summary_res = sql_query("SELECT COUNT(*) AS total FROM user_assessment_assignments");
$assignment_summary = mysql_fetch_assoc($assignment_summary_res);
$assignment_count = (int)($assignment_summary['total'] ?? 0);
$salary_summary_res = sql_query("SELECT COUNT(*) AS total, MAX(created_at) AS last_paid_at FROM uploader_salary_log");
$salary_summary = mysql_fetch_assoc($salary_summary_res);
$salary_log_count = (int)($salary_summary['total'] ?? 0);
$last_paid_at = $salary_summary['last_paid_at'] ?? null;
$assessment_type_count = count(array_unique(array_column($requirements, 'assessment_type')));

stdhead("绩效考核管理");
?>

<style>
    .assessment-container {
        text-align: center;
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .header-section {
        display: flex;
        align-items: center;
        justify-content: left;
        margin-bottom: 30px;
        background: rgb(254, 249, 241);
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        flex-wrap: wrap;
    }

    .header-text {
        text-align: left;
    }

    .header-text h1 {
        color: black;
        margin: 0 0 10px 0;
        font-size: 28px;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .header-text p {
        color: rgba(80, 80, 80, 0.9);
        margin: 0;
        font-size: 14px;
        line-height: 1.4;
    }

    .section {
        margin-bottom: 30px;
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
        text-align: left;
    }

    .section h3 {
        margin-top: 0;
        color: #333;
        border-bottom: 2px solid #007bff;
        padding-bottom: 10px;
    }

    .search-form {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .search-form input {
        flex: 1;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 16px;
    }

    .search-btn {
        padding: 10px 20px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
    }

    .search-btn:hover {
        background: #0056b3;
    }

    .search-btn[style*="background: #28a745"]:hover {
        background: #218838 !important;
    }

    .user-info {
        background: #e9ecef;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: none;
    }

    .user-info.show {
        display: block;
    }

    .assessment-list {
        margin-top: 20px;
    }

    .assessment-item {
        background: white;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 10px;
        border-left: 4px solid #007bff;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .assessment-item .info {
        flex: 1;
    }

    .assessment-item .actions {
        display: flex;
        gap: 10px;
    }

    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        text-decoration: none;
        display: inline-block;
        transition: background-color 0.3s;
    }

    .expand-btn {
        background: #007bff;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 3px;
        cursor: pointer;
        font-size: 12px;
        margin-left: 10px;
        transition: background-color 0.3s;
    }

    .expand-btn:hover {
        background: #0056b3;
    }

    .config-summary {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin-top: 15px;
        font-size: 13px;
        line-height: 1.6;
        display: none;
    }

    .config-summary h5 {
        margin-top: 0;
        color: #495057;
        border-bottom: 2px solid #007bff;
        padding-bottom: 8px;
    }

    .config-summary ul {
        margin: 10px 0;
        padding-left: 20px;
    }

    .config-summary li {
        margin: 5px 0;
    }

    .btn-primary {
        background: #007bff;
        color: white;
    }

    .btn-danger {
        background: #dc3545;
        color: white;
    }

    .btn-success {
        background: #28a745;
        color: white;
    }

    .btn:hover {
        opacity: 0.8;
    }

    .requirements-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .requirement-group {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 30px;
        border: 1px solid #e9ecef;
    }

    .group-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #007bff;
    }

    .group-header h4 {
        margin: 0;
        color: #007bff;
        font-size: 20px;
    }

    .requirement-card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        border: 1px solid #ddd;
        margin-bottom: 15px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border-left: 4px solid #007bff;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }

    .card-header h5 {
        margin: 0;
        color: #333;
        font-size: 16px;
    }

    .config-item {
        margin-bottom: 15px;
    }

    .config-item label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        color: #333;
    }

    .config-item input {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
    }

    .config-item .help-text {
        font-size: 12px;
        color: #666;
        margin-top: 3px;
    }

    .save-config-btn {
        background: #28a745;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        margin-top: 10px;
    }

    .save-config-btn:hover {
        background: #218838;
    }

    .result-message {
        margin-top: 15px;
        padding: 10px;
        border-radius: 5px;
        font-weight: bold;
    }

    .result-message.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .result-message.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .add-assessment-form {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-top: 20px;
        display: none;
    }

    .add-assessment-form.show {
        display: block;
    }

    .assessment-type-select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 16px;
        margin-bottom: 15px;
    }

    .warning-message {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
        padding: 10px;
        border-radius: 5px;
        margin-top: 10px;
        font-size: 14px;
    }

    .warning-message strong {
        color: #856404;
    }

    .info-message {
        background: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 10px;
        font-size: 14px;
    }

    .info-message strong {
        color: #0c5460;
    }

    /* NovaHD salary configuration workspace */
    .assessment-container {
        width: 94%;
        max-width: 1455px;
        padding: 0 0 32px;
        color: #18343d;
        text-align: left;
        box-sizing: border-box;
    }

    .assessment-container > .header-section {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        min-height: 104px;
        margin: 16px 0 14px;
        padding: 24px 28px;
        border: 1px solid rgba(255, 255, 255, .55);
        border-radius: 8px;
        background: linear-gradient(112deg, #102f3a 0%, #11555a 58%, #0f8276 100%);
        box-shadow: 0 12px 28px rgba(28, 73, 81, .15);
        box-sizing: border-box;
    }

    .assessment-container > .header-section h1 {
        margin: 0 0 7px;
        color: #fff !important;
        font-size: 25px;
        line-height: 1.3;
        text-shadow: none;
    }

    .assessment-container > .header-section h1::after {
        display: none !important;
        content: none !important;
    }

    .assessment-container > .header-section p {
        color: rgba(255, 255, 255, .78);
        font-size: 13px;
    }

    .assessment-header-actions {
        display: flex;
        gap: 8px;
        flex: 0 0 auto;
    }

    .assessment-header-actions a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 38px;
        padding: 0 16px;
        border: 1px solid rgba(255, 255, 255, .28);
        border-radius: 7px;
        background: rgba(255, 255, 255, .12);
        color: #fff !important;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
    }

    .assessment-header-actions a:hover {
        background: rgba(255, 255, 255, .2);
    }

    .assessment-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 14px;
        border: 1px solid #d5e5e7;
        border-radius: 8px;
        background: rgba(255, 255, 255, .9);
        box-shadow: 0 8px 22px rgba(36, 74, 83, .08);
        overflow: hidden;
    }

    .assessment-summary-item {
        min-width: 0;
        padding: 15px 18px;
        border-right: 1px solid #e2ecee;
    }

    .assessment-summary-item:last-child {
        border-right: 0;
    }

    .assessment-summary-item span,
    .assessment-summary-item strong {
        display: block;
    }

    .assessment-summary-item span {
        margin-bottom: 4px;
        color: #748b92;
        font-size: 11px;
        font-weight: 700;
    }

    .assessment-summary-item strong {
        overflow: hidden;
        color: #173943;
        font-size: 17px;
        line-height: 1.35;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .assessment-container > .section {
        margin: 0 0 14px;
        padding: 20px;
        border: 1px solid #d8e6e8;
        border-radius: 8px;
        background: rgba(255, 255, 255, .9);
        box-shadow: 0 6px 18px rgba(39, 73, 81, .07);
        box-sizing: border-box;
    }

    .assessment-container > .section > h3 {
        margin: 0 0 16px;
        padding: 0 0 11px;
        border-bottom: 1px solid #d9e6e8;
        color: #193c45 !important;
        font-size: 16px;
    }

    .assessment-container > .section > h3::after {
        display: none !important;
        content: none !important;
    }

    .assessment-container .search-form {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) auto auto;
        gap: 10px;
        margin: 0;
    }

    .assessment-container .search-form input {
        width: 100%;
        min-height: 40px !important;
        padding: 6px 11px !important;
        border-color: #c9dcde !important;
        border-radius: 7px !important;
        font-size: 13px;
        box-sizing: border-box;
    }

    .assessment-container button,
    .assessment-container .btn,
    .assessment-container .search-btn,
    .assessment-container .expand-btn {
        min-height: 38px;
        margin: 0;
        padding: 0 16px;
        border: 0;
        border-radius: 7px !important;
        background: #0f9f8d !important;
        color: #fff !important;
        font-size: 12px;
        font-weight: 800;
        line-height: 38px;
        text-align: center;
        text-decoration: none;
        box-shadow: 0 5px 12px rgba(15, 159, 141, .15) !important;
    }

    .assessment-container .btn-danger {
        background: #c2414a !important;
        box-shadow: 0 5px 12px rgba(194, 65, 74, .14) !important;
    }

    .assessment-container .expand-btn {
        min-height: 32px;
        margin-left: 8px;
        padding: 0 11px;
        background: #397b96 !important;
        line-height: 32px;
    }

    .assessment-container .user-info,
    .assessment-container .add-assessment-form {
        margin: 16px 0 0;
        padding: 16px;
        border: 1px solid #dce8ea;
        border-radius: 7px;
        background: #f8fbfb;
    }

    .assessment-container .assessment-item {
        margin: 8px 0;
        padding: 13px 14px;
        border: 1px solid #dce8ea;
        border-left: 3px solid #0f9f8d;
        border-radius: 6px;
        background: #fff;
    }

    .assessment-container .requirements-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
        margin: 0;
    }

    .assessment-container .requirement-group {
        min-width: 0;
        margin: 0;
        padding: 0;
        border: 0;
        background: transparent;
    }

    .assessment-container .group-header {
        margin: 0 0 10px;
        padding: 0 0 10px;
        border-bottom: 1px solid #cfdee1;
    }

    .assessment-container .group-header h4 {
        color: #176b68;
        font-size: 17px;
    }

    .assessment-container .requirement-card {
        margin: 0 0 12px;
        padding: 16px;
        border: 1px solid #d7e5e7;
        border-left: 3px solid #0f9f8d;
        border-radius: 7px;
        background: #fff;
        box-shadow: 0 5px 14px rgba(40, 75, 83, .06);
    }

    .assessment-container .card-header {
        margin-bottom: 14px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e3ecee;
    }

    .assessment-container .config-item {
        display: grid;
        grid-template-columns: minmax(120px, .85fr) minmax(120px, 1fr);
        gap: 4px 12px;
        align-items: center;
        margin-bottom: 11px;
    }

    .assessment-container .config-item label {
        margin: 0;
        color: #35525a;
        font-size: 12px;
    }

    .assessment-container .config-item input {
        width: 100%;
        min-height: 36px !important;
        padding: 5px 9px !important;
        border-color: #cbdde0 !important;
        border-radius: 6px !important;
        font-size: 12px;
        box-sizing: border-box;
    }

    .assessment-container .config-item input.is-saving {
        border-color: #d89b2b !important;
        box-shadow: 0 0 0 3px rgba(216, 155, 43, .12) !important;
    }

    .assessment-container .config-item input.is-saved {
        border-color: #16a085 !important;
        box-shadow: 0 0 0 3px rgba(22, 160, 133, .12) !important;
    }

    .assessment-container .config-item .help-text {
        grid-column: 2;
        color: #7a8e94;
        font-size: 11px;
        line-height: 1.45;
    }

    .assessment-config-notice {
        margin: 0 0 16px;
        padding: 10px 12px;
        border: 1px solid #cce2df;
        border-radius: 6px;
        background: #f0faf8;
        color: #3c6865;
        font-size: 12px;
    }

    #result-message {
        position: sticky;
        top: 8px;
        z-index: 20;
    }

    .assessment-container .result-message {
        margin: 0 0 12px;
        padding: 10px 13px;
        border-radius: 6px;
        box-shadow: 0 7px 18px rgba(36, 70, 78, .1);
    }

    @media (max-width: 1000px) {
        .assessment-container .requirements-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    @media (max-width: 720px) {
        .assessment-container {
            width: calc(100% - 18px);
        }

        .assessment-container > .header-section {
            align-items: stretch;
            flex-direction: column;
            min-height: 0;
            padding: 20px;
        }

        .assessment-header-actions a {
            width: 100%;
            box-sizing: border-box;
        }

        .assessment-summary,
        .assessment-container .search-form {
            grid-template-columns: minmax(0, 1fr);
        }

        .assessment-summary-item {
            border-right: 0;
            border-bottom: 1px solid #e2ecee;
        }

        .assessment-summary-item:last-child {
            border-bottom: 0;
        }

        .assessment-container .config-item {
            grid-template-columns: minmax(0, 1fr);
        }

        .assessment-container .config-item .help-text {
            grid-column: 1;
        }
    }
</style>

<div class="assessment-container">
    <div class="header-section">
        <div class="header-text">
            <h1>工资与考核配置</h1>
            <p>管理岗位配置、考核要求和用户分配，修改配置项后自动保存</p>
        </div>
        <div class="assessment-header-actions">
            <a href="view_salary.php">返回工资总览</a>
        </div>
    </div>

    <div class="assessment-summary">
        <div class="assessment-summary-item"><span>岗位类型</span><strong><?= number_format($assessment_type_count) ?></strong></div>
        <div class="assessment-summary-item"><span>工资配置</span><strong><?= number_format(count($requirements)) ?></strong></div>
        <div class="assessment-summary-item"><span>已分配人员</span><strong><?= number_format($assignment_count) ?></strong></div>
        <div class="assessment-summary-item"><span>最近发放</span><strong><?= $last_paid_at ? htmlspecialchars($last_paid_at) : '暂无记录' ?></strong></div>
    </div>

    <!-- 用户搜索区域 -->
    <div class="section">
        <h3>🔍 用户搜索</h3>
        <div class="search-form">
            <input type="text" id="search-term" placeholder="输入用户名或UID进行搜索..." maxlength="40">
            <button type="button" class="search-btn" onclick="searchUser()">搜索用户</button>
            <button type="button" class="search-btn" onclick="loadUploaderUsers()" style="background: #28a745;">显示Uploader+用户</button>
        </div>

        <div id="user-info" class="user-info">
            <h4>用户信息</h4>
            <div id="user-details"></div>

            <div id="current-assessments" class="assessment-list">
                <h4>当前考核分配</h4>
                <div id="assessments-list"></div>
            </div>

            <div id="add-assessment-form" class="add-assessment-form">
                <h4>添加考核分配</h4>
                <p style="font-size: 12px; color: #666; margin-bottom: 10px;">注意：一个用户最多只能被分配一个考核类型（在添加考核前最好为用户分配头衔）</p>
                <select id="requirement-select" class="assessment-type-select">
                    <option value="">请选择考核配置</option>
                    <?php foreach ($requirements as $req): ?>
                        <option value="<?= $req['id'] ?>"><?= htmlspecialchars($req['assessment_name']) ?> - <?= htmlspecialchars($req['config_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-success" onclick="addAssessment()">添加考核</button>
            </div>
        </div>
    </div>

    <!-- 考核配置区域 -->
    <div class="section">
        <h3>⚙️ 考核要求配置</h3>
        <div class="assessment-config-notice">输入框失去焦点后自动保存。最低要求用于决定基础工资，考核要求用于计算额外奖励，奖励比例必须大于0。</div>
        <div id="result-message"></div>
        <div class="requirements-grid">
            <?php
            // 按考核类型分组显示
            $grouped_requirements = array();
            foreach ($requirements as $req) {
                $type = $req['assessment_type'];
                if (!isset($grouped_requirements[$type])) {
                    $grouped_requirements[$type] = array();
                }
                $grouped_requirements[$type][] = $req;
            }

            foreach ($grouped_requirements as $type => $type_requirements):
                $first_req = $type_requirements[0];
            ?>

                <div class="requirement-group">
                    <div class="group-header">
                        <h4><?= htmlspecialchars($first_req['assessment_name']) ?></h4>
                        <button class="btn btn-success btn-sm" onclick="showAddConfigModal(<?= $type ?>, '<?= htmlspecialchars($first_req['assessment_name']) ?>')">
                            + 添加配置
                        </button>
                    </div>

                    <?php foreach ($type_requirements as $req): ?>
                        <div class="requirement-card" data-requirement-id="<?= $req['id'] ?>">
                            <div class="card-header">
                                <h5><?= htmlspecialchars($req['config_name']) ?></h5>
                                <?php if ($req['config_name'] != '默认配置'): ?>
                                    <button class="btn btn-danger btn-sm" onclick="deleteConfig(<?= $req['id'] ?>, '<?= htmlspecialchars($req['config_name']) ?>')">
                                        删除
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- 基础配置 -->
                            <div class="config-item">
                                <label>基础工资（魔力值）</label>
                                <input type="number" name="base_salary" value="<?= $req['base_salary'] ?>" defaultValue="<?= $req['base_salary'] ?>"
                                    onchange="updateRequirement(<?= $req['id'] ?>, 'base_salary', this.value)" min="0">
                            </div>

                            <div class="config-item">
                                <label>要求时间间隔（秒，0代表永久）</label>
                                <input type="number" name="interval_seconds" value="<?= $req['interval_seconds'] ?>" defaultValue="<?= $req['interval_seconds'] ?>"
                                    onchange="updateRequirement(<?= $req['id'] ?>, 'interval_seconds', this.value)" min="0">
                                <div class="help-text">常用时间：7天=604800秒，30天=2592000秒，1年=31536000秒</div>
                            </div>

                            <?php if ($req['assessment_type'] == 1): // 发布员/转种员 
                            ?>
                                <div class="config-item">
                                    <label>发布数量要求</label>
                                    <input type="number" name="publish_quantity_req" value="<?= $req['publish_quantity_req'] ?>" defaultValue="<?= $req['publish_quantity_req'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'publish_quantity_req', this.value)" min="0">
                                    <div class="help-text">用于检测低于这个要求将提醒，0表示无要求</div>
                                </div>

                                <div class="config-item">
                                    <label>最低发布数量要求</label>
                                    <input type="number" name="min_publish_quantity_req" value="<?= $req['min_publish_quantity_req'] ?>" defaultValue="<?= $req['min_publish_quantity_req'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'min_publish_quantity_req', this.value)" min="0">
                                    <div class="help-text">用于检测低于这个要求将无法领取工资甚至移除身份</div>
                                </div>

                                <div class="config-item">
                                    <label>发布体积要求（GB）</label>
                                    <input type="number" step="0.01" name="publish_volume_req" value="<?= $req['publish_volume_req'] ?>" defaultValue="<?= $req['publish_volume_req'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'publish_volume_req', this.value)" min="0">
                                    <div class="help-text">用于检测低于这个要求将提醒，0表示无要求</div>
                                </div>

                                <div class="config-item">
                                    <label>最低发布体积要求（GB）</label>
                                    <input type="number" step="0.01" name="min_publish_volume_req" value="<?= $req['min_publish_volume_req'] ?>" defaultValue="<?= $req['min_publish_volume_req'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'min_publish_volume_req', this.value)" min="0">
                                    <div class="help-text">用于检测低于这个要求将无法领取工资甚至移除身份</div>
                                </div>

                                <div class="config-item">
                                    <label>超过要求奖励金额</label>
                                    <input type="number" name="publish_bonus_amount" value="<?= $req['publish_bonus_amount'] ?>" defaultValue="<?= $req['publish_bonus_amount'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'publish_bonus_amount', this.value)" min="0">
                                    <div class="help-text">基础奖励金额，如1000</div>
                                </div>

                                <div class="config-item">
                                    <label>奖励数量比例</label>
                                    <input type="number" name="publish_bonus_qty_ratio" value="<?= $req['publish_bonus_qty_ratio'] ?>" defaultValue="<?= $req['publish_bonus_qty_ratio'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'publish_bonus_qty_ratio', this.value)" min="1">
                                    <div class="help-text">数量比例，如1</div>
                                </div>

                                <div class="config-item">
                                    <label>奖励体积比例（GB）</label>
                                    <input type="number" step="0.01" name="publish_bonus_vol_ratio" value="<?= $req['publish_bonus_vol_ratio'] ?>" defaultValue="<?= $req['publish_bonus_vol_ratio'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'publish_bonus_vol_ratio', this.value)" min="0">
                                    <div class="help-text">体积比例，如2</div>
                                </div>

                                <div class="config-item">
                                    <label>最多获得超出要求奖励</label>
                                    <input type="number" name="publish_max_bonus" value="<?= $req['publish_max_bonus'] ?>" defaultValue="<?= $req['publish_max_bonus'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'publish_max_bonus', this.value)" min="0">
                                    <div class="help-text">超出要求的最大奖励限制</div>
                                </div>

                                <div style="background: #e9ecef; padding: 10px; border-radius: 5px; margin-top: 15px; font-size: 12px;">
                                    <strong>奖励计算说明：</strong><br>
                                    格式：<?= $req['publish_bonus_amount'] ?>/<?= $req['publish_bonus_qty_ratio'] ?>:<?= $req['publish_bonus_vol_ratio'] ?><br>
                                    代表每超过发布数量要求<?= $req['publish_bonus_qty_ratio'] ?>个并且发布体积要求<?= $req['publish_bonus_vol_ratio'] ?>GB获得<?= $req['publish_bonus_amount'] ?>魔力
                                    <button class="expand-btn" onclick="toggleConfigSummary(<?= $req['id'] ?>, 'publisher')">📋 查看配置总结</button>
                                </div>

                                <div id="config-summary-<?= $req['id'] ?>" class="config-summary">
                                    <h5>📊 <?= htmlspecialchars($req['config_name']) ?> 配置总结</h5>
                                    <ul>
                                        <li><strong>基础工资：</strong><?= number_format($req['base_salary']) ?> 魔力值</li>
                                        <li><strong>考核周期：</strong><?= $req['interval_seconds'] == 0 ? '永久' : '每' . ($req['interval_seconds'] / 86400) . '天' ?></li>
                                        <li><strong>发布数量要求：</strong><?= number_format($req['publish_quantity_req']) ?> 个（用于计算额外奖励）</li>
                                        <li><strong>最低发布数量要求：</strong><?= number_format($req['min_publish_quantity_req']) ?> 个（低于此值无法获得基本工资甚至移除身份）</li>
                                        <li><strong>发布体积要求：</strong><?= number_format($req['publish_volume_req'], 2) ?> GB（用于计算额外奖励）</li>
                                        <li><strong>最低发布体积要求：</strong><?= number_format($req['min_publish_volume_req'], 2) ?> GB（低于此值无法获得基本工资甚至移除身份）</li>
                                        <li><strong>奖励计算：</strong>每超过 <?= $req['publish_bonus_qty_ratio'] ?> 个 + <?= number_format($req['publish_bonus_vol_ratio'], 2) ?> GB 获得 <?= number_format($req['publish_bonus_amount']) ?> 魔力值</li>
                                        <li><strong>奖励上限：</strong><?= number_format($req['publish_max_bonus']) ?> 魔力值</li>
                                    </ul>
                                    <p><strong>说明：</strong>只要符合最低要求就得基础工资。数量要求必须同时满足才能获得额外奖励，取最小值计算。超出奖励上限的部分不累计。</p>
                                </div>

                            <?php elseif ($req['assessment_type'] == 2): // 保种员 
                            ?>
                                <div class="config-item">
                                    <label>保种数量要求</label>
                                    <input type="number" name="seed_quantity_req" value="<?= $req['seed_quantity_req'] ?>" defaultValue="<?= $req['seed_quantity_req'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'seed_quantity_req', this.value)" min="0">
                                    <div class="help-text">用于检测低于这个要求将提醒，0表示无要求</div>
                                </div>

                                <div class="config-item">
                                    <label>最低保种数量要求</label>
                                    <input type="number" name="min_seed_quantity_req" value="<?= $req['min_seed_quantity_req'] ?>" defaultValue="<?= $req['min_seed_quantity_req'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'min_seed_quantity_req', this.value)" min="0">
                                    <div class="help-text">用于检测低于这个要求将无法领取工资甚至移除身份</div>
                                </div>

                                <div class="config-item">
                                    <label>保种体积要求（GB）</label>
                                    <input type="number" step="0.01" name="seed_volume_req" value="<?= $req['seed_volume_req'] ?>" defaultValue="<?= $req['seed_volume_req'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'seed_volume_req', this.value)" min="0">
                                    <div class="help-text">用于检测低于这个要求将提醒，0表示无要求</div>
                                </div>

                                <div class="config-item">
                                    <label>最低保种体积要求（GB）</label>
                                    <input type="number" step="0.01" name="min_seed_volume_req" value="<?= $req['min_seed_volume_req'] ?>" defaultValue="<?= $req['min_seed_volume_req'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'min_seed_volume_req', this.value)" min="0">
                                    <div class="help-text">用于检测低于这个要求将无法领取工资甚至移除身份</div>
                                </div>

                                <div class="config-item">
                                    <label>超过要求奖励金额</label>
                                    <input type="number" name="seed_bonus_amount" value="<?= $req['seed_bonus_amount'] ?>" defaultValue="<?= $req['seed_bonus_amount'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'seed_bonus_amount', this.value)" min="0">
                                    <div class="help-text">基础奖励金额，如1000</div>
                                </div>

                                <div class="config-item">
                                    <label>奖励数量比例</label>
                                    <input type="number" name="seed_bonus_qty_ratio" value="<?= $req['seed_bonus_qty_ratio'] ?>" defaultValue="<?= $req['seed_bonus_qty_ratio'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'seed_bonus_qty_ratio', this.value)" min="1">
                                    <div class="help-text">数量比例，如1</div>
                                </div>

                                <div class="config-item">
                                    <label>奖励体积比例（GB）</label>
                                    <input type="number" step="0.01" name="seed_bonus_vol_ratio" value="<?= $req['seed_bonus_vol_ratio'] ?>" defaultValue="<?= $req['seed_bonus_vol_ratio'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'seed_bonus_vol_ratio', this.value)" min="0">
                                    <div class="help-text">体积比例，如100</div>
                                </div>

                                <div class="config-item">
                                    <label>最多获得超出要求奖励</label>
                                    <input type="number" name="seed_max_bonus" value="<?= $req['seed_max_bonus'] ?>" defaultValue="<?= $req['seed_max_bonus'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'seed_max_bonus', this.value)" min="0">
                                    <div class="help-text">超出要求的最大奖励限制</div>
                                </div>

                                <div style="background: #e9ecef; padding: 10px; border-radius: 5px; margin-top: 15px; font-size: 12px;">
                                    <strong>奖励计算说明：</strong><br>
                                    格式：<?= $req['seed_bonus_amount'] ?>/<?= $req['seed_bonus_qty_ratio'] ?>:<?= $req['seed_bonus_vol_ratio'] ?><br>
                                    代表每超过保种数量要求<?= $req['seed_bonus_qty_ratio'] ?>个并且保种体积要求<?= $req['seed_bonus_vol_ratio'] ?>GB获得<?= $req['seed_bonus_amount'] ?>魔力
                                    <button class="expand-btn" onclick="toggleConfigSummary(<?= $req['id'] ?>, 'keeper')">📋 查看配置总结</button>
                                </div>

                                <div id="config-summary-<?= $req['id'] ?>" class="config-summary">
                                    <h5>📊 <?= htmlspecialchars($req['config_name']) ?> 配置总结</h5>
                                    <ul>
                                        <li><strong>基础工资：</strong><?= number_format($req['base_salary']) ?> 魔力值</li>
                                        <li><strong>考核周期：</strong><?= $req['interval_seconds'] == 0 ? '永久' : '每' . ($req['interval_seconds'] / 86400) . '天' ?></li>
                                        <li><strong>保种数量要求：</strong><?= number_format($req['seed_quantity_req']) ?> 个（用于计算额外奖励）</li>
                                        <li><strong>最低保种数量要求：</strong><?= number_format($req['min_seed_quantity_req']) ?> 个（低于此值无法获得基本工资甚至移除身份）</li>
                                        <li><strong>保种体积要求：</strong><?= number_format($req['seed_volume_req'], 2) ?> GB（用于计算额外奖励）</li>
                                        <li><strong>最低保种体积要求：</strong><?= number_format($req['min_seed_volume_req'], 2) ?> GB（低于此值无法获得基本工资甚至移除身份）</li>
                                        <li><strong>奖励计算：</strong>每超过 <?= $req['seed_bonus_qty_ratio'] ?> 个 + <?= number_format($req['seed_bonus_vol_ratio'], 2) ?> GB 获得 <?= number_format($req['seed_bonus_amount']) ?> 魔力值</li>
                                        <li><strong>奖励上限：</strong><?= number_format($req['seed_max_bonus']) ?> 魔力值</li>
                                    </ul>
                                    <p><strong>说明：</strong>只要符合最低要求就得基础工资。数量要求必须同时满足才能获得额外奖励，取最小值计算。超出奖励上限的部分不累计。</p>
                                </div>

                            <?php elseif ($req['assessment_type'] == 3): // 审核员 
                            ?>
                                <div class="config-item">
                                    <label>操作总数要求</label>
                                    <input type="number" name="audit_total_req" value="<?= $req['audit_total_req'] ?>" defaultValue="<?= $req['audit_total_req'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'audit_total_req', this.value)" min="0">
                                    <div class="help-text">用于检测低于这个要求将提醒，0表示无要求</div>
                                </div>

                                <div class="config-item">
                                    <label>最低操作总数要求</label>
                                    <input type="number" name="min_audit_total_req" value="<?= $req['min_audit_total_req'] ?>" defaultValue="<?= $req['min_audit_total_req'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'min_audit_total_req', this.value)" min="0">
                                    <div class="help-text">用于检测低于这个要求将无法领取工资甚至移除身份</div>
                                </div>

                                <div class="config-item">
                                    <label>通过审核要求</label>
                                    <input type="number" name="audit_pass_req" value="<?= $req['audit_pass_req'] ?>" defaultValue="<?= $req['audit_pass_req'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'audit_pass_req', this.value)" min="0">
                                    <div class="help-text">用于检测低于这个要求将提醒，0表示无要求</div>
                                </div>

                                <div class="config-item">
                                    <label>最低通过审核要求</label>
                                    <input type="number" name="min_audit_pass_req" value="<?= $req['min_audit_pass_req'] ?>" defaultValue="<?= $req['min_audit_pass_req'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'min_audit_pass_req', this.value)" min="0">
                                    <div class="help-text">用于检测低于这个要求将无法领取工资甚至移除身份</div>
                                </div>

                                <div class="config-item">
                                    <label>超过要求奖励金额</label>
                                    <input type="number" name="audit_bonus_amount" value="<?= $req['audit_bonus_amount'] ?>" defaultValue="<?= $req['audit_bonus_amount'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'audit_bonus_amount', this.value)" min="0">
                                    <div class="help-text">基础奖励金额，如500</div>
                                </div>

                                <div class="config-item">
                                    <label>奖励操作总数比例</label>
                                    <input type="number" name="audit_bonus_total_ratio" value="<?= $req['audit_bonus_total_ratio'] ?>" defaultValue="<?= $req['audit_bonus_total_ratio'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'audit_bonus_total_ratio', this.value)" min="1">
                                    <div class="help-text">操作总数比例，如2</div>
                                </div>

                                <div class="config-item">
                                    <label>奖励通过审核比例</label>
                                    <input type="number" name="audit_bonus_pass_ratio" value="<?= $req['audit_bonus_pass_ratio'] ?>" defaultValue="<?= $req['audit_bonus_pass_ratio'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'audit_bonus_pass_ratio', this.value)" min="1">
                                    <div class="help-text">通过审核比例，如1</div>
                                </div>

                                <div class="config-item">
                                    <label>最多获得超出要求奖励</label>
                                    <input type="number" name="audit_max_bonus" value="<?= $req['audit_max_bonus'] ?>" defaultValue="<?= $req['audit_max_bonus'] ?>"
                                        onchange="updateRequirement(<?= $req['id'] ?>, 'audit_max_bonus', this.value)" min="0">
                                    <div class="help-text">超出要求的最大奖励限制</div>
                                </div>

                                <div style="background: #e9ecef; padding: 10px; border-radius: 5px; margin-top: 15px; font-size: 12px;">
                                    <strong>奖励计算说明：</strong><br>
                                    格式：<?= $req['audit_bonus_amount'] ?>/<?= $req['audit_bonus_total_ratio'] ?>:<?= $req['audit_bonus_pass_ratio'] ?><br>
                                    代表每超过操作总数要求<?= $req['audit_bonus_total_ratio'] ?>个并且通过审核要求<?= $req['audit_bonus_pass_ratio'] ?>个获得<?= $req['audit_bonus_amount'] ?>魔力
                                    <button class="expand-btn" onclick="toggleConfigSummary(<?= $req['id'] ?>, 'auditor')">📋 查看配置总结</button>
                                </div>

                                <div id="config-summary-<?= $req['id'] ?>" class="config-summary">
                                    <h5>📊 <?= htmlspecialchars($req['config_name']) ?> 配置总结</h5>
                                    <ul>
                                        <li><strong>基础工资：</strong><?= number_format($req['base_salary']) ?> 魔力值</li>
                                        <li><strong>考核周期：</strong><?= $req['interval_seconds'] == 0 ? '永久' : '每' . ($req['interval_seconds'] / 86400) . '天' ?></li>
                                        <li><strong>操作总数要求：</strong><?= number_format($req['audit_total_req']) ?> 次（用于计算额外奖励）</li>
                                        <li><strong>最低操作总数要求：</strong><?= number_format($req['min_audit_total_req']) ?> 次（低于此值无法获得基本工资甚至移除身份）</li>
                                        <li><strong>通过审核要求：</strong><?= number_format($req['audit_pass_req']) ?> 次（用于计算额外奖励）</li>
                                        <li><strong>最低通过审核要求：</strong><?= number_format($req['min_audit_pass_req']) ?> 次（低于此值无法获得基本工资甚至移除身份）</li>
                                        <li><strong>奖励计算：</strong>每超过 <?= $req['audit_bonus_total_ratio'] ?> 次操作 + <?= $req['audit_bonus_pass_ratio'] ?> 次通过审核 获得 <?= number_format($req['audit_bonus_amount']) ?> 魔力值</li>
                                        <li><strong>奖励上限：</strong><?= number_format($req['audit_max_bonus']) ?> 魔力值</li>
                                    </ul>
                                    <p><strong>说明：</strong>只要符合最低要求就得基础工资。操作总数和通过审核要求必须同时满足才能获得额外奖励，取最小值计算。超出奖励上限的部分不累计。</p>
                                </div>

                            <?php elseif ($req['assessment_type'] == 4): // 其他 
                            ?>
                                <div style="background: #e9ecef; padding: 10px; border-radius: 5px; margin-top: 15px; font-size: 12px;">
                                    <strong>说明：</strong><br>
                                    此配置适用于Uploader以上级别无考核的工资配置<br>
                                    只包含基础工资和时间间隔设置
                                    <button class="expand-btn" onclick="toggleConfigSummary(<?= $req['id'] ?>, 'other')">📋 查看配置总结</button>
                                </div>

                                <div id="config-summary-<?= $req['id'] ?>" class="config-summary">
                                    <h5>📊 <?= htmlspecialchars($req['config_name']) ?> 配置总结</h5>
                                    <ul>
                                        <li><strong>基础工资：</strong><?= number_format($req['base_salary']) ?> 魔力值</li>
                                        <li><strong>考核周期：</strong><?= $req['interval_seconds'] == 0 ? '永久' : '每' . ($req['interval_seconds'] / 86400) . '天' ?></li>
                                        <li><strong>考核要求：</strong>无需特殊要求，达到时间间隔即可领取基础工资</li>
                                        <li><strong>额外奖励：</strong>无额外奖励机制</li>
                                    </ul>
                                    <p><strong>说明：</strong>此配置适用于Uploader以上级别无考核的工资配置，只包含基础工资和领取时间间隔设置。</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>


</div>

<script>
    let currentUserId = null;

    function searchUser() {
        const searchTerm = document.getElementById('search-term').value.trim();
        if (!searchTerm) {
            showMessage('请输入用户名或UID', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'search_user');
        formData.append('search_term', searchTerm);

        fetch('staff_assessment.php', {
            method: 'POST',
            body: formData
        }).then(r => r.json()).then(res => {
            if (res.success) {
                if (res.users.length === 0) {
                    showMessage('未找到匹配的用户', 'error');
                    return;
                }

                // 如果只有一个用户，直接显示
                if (res.users.length === 1) {
                    displayUserInfo(res.users[0]);
                } else {
                    // 多个用户，显示选择列表
                    showUserSelection(res.users);
                }
            } else {
                showMessage(res.message, 'error');
            }
        }).catch(error => {
            showMessage('搜索失败，请稍后重试', 'error');
        });
    }

    function loadUploaderUsers() {
        const formData = new FormData();
        formData.append('action', 'get_uploader_users');

        fetch('staff_assessment.php', {
            method: 'POST',
            body: formData
        }).then(r => r.json()).then(res => {
            if (res.success) {
                if (res.users.length === 0) {
                    showMessage('没有找到Uploader及以上身份的用户', 'info');
                    return;
                }

                // 显示用户列表
                showUserSelection(res.users);
            } else {
                showMessage(res.message, 'error');
            }
        }).catch(error => {
            showMessage('加载失败，请稍后重试', 'error');
        });
    }

    function showUserSelection(users) {
        let html = '<h4>用户列表：</h4>';

        // 按等级分组显示用户
        const groupedUsers = {};
        users.forEach(user => {
            const classText = user.class_text;
            if (!groupedUsers[classText]) {
                groupedUsers[classText] = [];
            }
            groupedUsers[classText].push(user);
        });

        // 按等级顺序显示（从高到低）
        const classOrder = ['Staff Leader', 'SysOp', 'Administrator', 'Moderator', 'Uploader', 'Retiree', 'Vip', 'Nexus Master', 'Ultimate User', 'Extreme User', 'Veteran User', 'Insane User', 'Crazy User', 'Elite User', 'Power User', 'User', 'Peasant'];

        classOrder.forEach(classText => {
            if (groupedUsers[classText] && groupedUsers[classText].length > 0) {
                html += `<div style="margin-bottom: 20px;">`;
                html += `<h5 style="color: #007bff; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 10px;">${classText} (${groupedUsers[classText].length}人)</h5>`;

                groupedUsers[classText].forEach(user => {
                    let assessmentInfo = '';
                    if (user.current_assessment) {
                        assessmentInfo = `<br><span style="color: #007bff; font-weight: bold;">当前考核: ${user.current_assessment.assessment_name} - ${user.current_assessment.config_name || '默认配置'}</span>`;
                    }

                    html += `<div class="assessment-item">
                    <div class="info">
                        <a href="userdetails.php?id=${user.id}">${user.username}</a> (ID: ${user.id})<br>
                        等级: ${user.class_text} | 头衔: ${user.title || '无'}${assessmentInfo}
                    </div>
                    <div class="actions">
                        <button class="btn btn-primary" onclick="displayUserInfo(${JSON.stringify(user).replace(/"/g, '&quot;')})">选择</button>
                    </div>
                </div>`;
                });

                html += `</div>`;
            }
        });

        document.getElementById('user-details').innerHTML = html;
        document.getElementById('user-info').classList.add('show');
    }

    function displayUserInfo(user) {
        currentUserId = user.id;

        let assessmentInfo = '';
        if (user.current_assessment) {
            assessmentInfo = `<br><span style="color: #007bff; font-weight: bold;">当前考核: ${user.current_assessment.assessment_name} - ${user.current_assessment.config_name || '默认配置'}</span>`;
        }

        let html = `<div class="assessment-item">
        <div class="info">
            <a href="userdetails.php?id=${user.id}">${user.username}</a> (ID: ${user.id})<br>
            等级: ${user.class_text} | 头衔: ${user.title || '无'}${assessmentInfo}
        </div>
    </div>`;

        document.getElementById('user-details').innerHTML = html;
        document.getElementById('user-info').classList.add('show');

        // 加载用户的考核分配
        loadUserAssessments(user.id);
    }

    function loadUserAssessments(uid) {
        const formData = new FormData();
        formData.append('action', 'get_user_assessments');
        formData.append('uid', uid);

        fetch('staff_assessment.php', {
            method: 'POST',
            body: formData
        }).then(r => r.json()).then(res => {
            if (res.success) {
                displayAssessments(res.assignments);
            } else {
                showMessage(res.message, 'error');
            }
        }).catch(error => {
            showMessage('加载考核信息失败', 'error');
        });
    }

    function displayAssessments(assignments) {
        let html = '';
        if (assignments.length === 0) {
            html = '<div class="info-message">';
            html += '<strong>ℹ️ 信息：</strong>该用户暂无考核分配，可以为其添加考核。';
            html += '</div>';
            // 显示添加考核表单
            document.getElementById('add-assessment-form').classList.add('show');
        } else {
            assignments.forEach(assignment => {
                html += `<div class="assessment-item">
                <div class="info">
                    <strong>${assignment.assessment_name} - ${assignment.config_name || '默认配置'}</strong><br>
                    分配时间: ${assignment.created_at}
                </div>
                <div class="actions">
                    <button class="btn btn-danger" onclick="removeAssignment(${assignment.id})">移除</button>
                </div>
            </div>`;
            });

            // 隐藏添加考核表单，因为用户已有考核
            document.getElementById('add-assessment-form').classList.remove('show');

            // 显示提示信息
            html += '<div class="warning-message">';
            html += '<strong>⚠️ 提示：</strong>该用户已有考核分配，如需更换考核类型，请先移除当前考核。';
            html += '</div>';
        }

        document.getElementById('assessments-list').innerHTML = html;
    }

    function addAssessment() {
        const requirementId = document.getElementById('requirement-select').value;
        if (!requirementId) {
            showMessage('请选择考核配置', 'error');
            return;
        }

        if (!currentUserId) {
            showMessage('请先选择用户', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'add_assessment');
        formData.append('uid', currentUserId);
        formData.append('requirement_id', requirementId);

        fetch('staff_assessment.php', {
            method: 'POST',
            body: formData
        }).then(r => r.json()).then(res => {
            if (res.success) {
                showMessage(res.message, 'success');
                // 重新加载用户考核信息，这会自动更新界面状态
                loadUserAssessments(currentUserId);
                document.getElementById('requirement-select').value = '';
            } else {
                showMessage(res.message, 'error');
            }
        }).catch(error => {
            showMessage('添加失败，请稍后重试', 'error');
        });
    }

    function removeAssignment(assignmentId) {
        if (!confirm('确定要移除这个考核分配吗？')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'remove_assignment');
        formData.append('assignment_id', assignmentId);

        fetch('staff_assessment.php', {
            method: 'POST',
            body: formData
        }).then(r => r.json()).then(res => {
            if (res.success) {
                showMessage(res.message, 'success');
                // 重新加载用户考核信息，这会自动更新界面状态
                loadUserAssessments(currentUserId);
            } else {
                showMessage(res.message, 'error');
            }
        }).catch(error => {
            showMessage('移除失败，请稍后重试', 'error');
        });
    }

    function updateRequirement(requirementId, field, value) {
        // 验证最低要求不能大于一般要求
        const card = document.querySelector(`[data-requirement-id="${requirementId}"]`);
        if (!card) {
            showMessage('找不到对应的配置卡片', 'error');
            return;
        }

        let isValid = true;
        let errorMessage = '';

        // 发布员/转种员验证
        if (field === 'publish_quantity_req') {
            const minReq = card.querySelector('input[name="min_publish_quantity_req"]');
            if (minReq && parseInt(value) < parseInt(minReq.value)) {
                isValid = false;
                errorMessage = '发布数量要求不能小于最低发布数量要求';
            }
        } else if (field === 'min_publish_quantity_req') {
            const req = card.querySelector('input[name="publish_quantity_req"]');
            if (req && parseInt(value) > parseInt(req.value)) {
                isValid = false;
                errorMessage = '最低发布数量要求不能大于发布数量要求';
            }
        } else if (field === 'publish_volume_req') {
            const minReq = card.querySelector('input[name="min_publish_volume_req"]');
            if (minReq && parseFloat(value) < parseFloat(minReq.value)) {
                isValid = false;
                errorMessage = '发布体积要求不能小于最低发布体积要求';
            }
        } else if (field === 'min_publish_volume_req') {
            const req = card.querySelector('input[name="publish_volume_req"]');
            if (req && parseFloat(value) > parseFloat(req.value)) {
                isValid = false;
                errorMessage = '最低发布体积要求不能大于发布体积要求';
            }
        }
        // 保种员验证
        else if (field === 'seed_quantity_req') {
            const minReq = card.querySelector('input[name="min_seed_quantity_req"]');
            if (minReq && parseInt(value) < parseInt(minReq.value)) {
                isValid = false;
                errorMessage = '保种数量要求不能小于最低保种数量要求';
            }
        } else if (field === 'min_seed_quantity_req') {
            const req = card.querySelector('input[name="seed_quantity_req"]');
            if (req && parseInt(value) > parseInt(req.value)) {
                isValid = false;
                errorMessage = '最低保种数量要求不能大于保种数量要求';
            }
        } else if (field === 'seed_volume_req') {
            const minReq = card.querySelector('input[name="min_seed_volume_req"]');
            if (minReq && parseFloat(value) < parseFloat(minReq.value)) {
                isValid = false;
                errorMessage = '保种体积要求不能小于最低保种体积要求';
            }
        } else if (field === 'min_seed_volume_req') {
            const req = card.querySelector('input[name="seed_volume_req"]');
            if (req && parseFloat(value) > parseFloat(req.value)) {
                isValid = false;
                errorMessage = '最低保种体积要求不能大于保种体积要求';
            }
        }
        // 审核员验证
        else if (field === 'audit_total_req') {
            const minReq = card.querySelector('input[name="min_audit_total_req"]');
            if (minReq && parseInt(value) < parseInt(minReq.value)) {
                isValid = false;
                errorMessage = '操作总数要求不能小于最低操作总数要求';
            }
        } else if (field === 'min_audit_total_req') {
            const req = card.querySelector('input[name="audit_total_req"]');
            if (req && parseInt(value) > parseInt(req.value)) {
                isValid = false;
                errorMessage = '最低操作总数要求不能大于操作总数要求';
            }
        } else if (field === 'audit_pass_req') {
            const minReq = card.querySelector('input[name="min_audit_pass_req"]');
            if (minReq && parseInt(value) < parseInt(minReq.value)) {
                isValid = false;
                errorMessage = '通过审核要求不能小于最低通过审核要求';
            }
        } else if (field === 'min_audit_pass_req') {
            const req = card.querySelector('input[name="audit_pass_req"]');
            if (req && parseInt(value) > parseInt(req.value)) {
                isValid = false;
                errorMessage = '最低通过审核要求不能大于通过审核要求';
            }
        }

        if (!isValid) {
            showMessage(errorMessage, 'error');
            // 恢复原值
            const input = card.querySelector(`input[name="${field}"]`);
            if (input) {
                input.value = input.defaultValue;
            }
            return;
        }

        const formData = new FormData();
        formData.append('action', 'update_requirement');
        formData.append('requirement_id', requirementId);
        formData.append('field', field);
        formData.append('value', value);

        const activeInput = card.querySelector(`input[name="${field}"]`);
        if (activeInput) {
            activeInput.classList.remove('is-saved');
            activeInput.classList.add('is-saving');
        }

        fetch('staff_assessment.php', {
            method: 'POST',
            body: formData
        }).then(r => r.json()).then(res => {
            if (res.success) {
                showMessage(res.message, 'success');
                // 更新默认值
                const input = card.querySelector(`input[name="${field}"]`);
                if (input) {
                    input.defaultValue = value;
                    input.classList.remove('is-saving');
                    input.classList.add('is-saved');
                    setTimeout(() => input.classList.remove('is-saved'), 1200);
                }
            } else {
                showMessage(res.message, 'error');
                // 恢复原值
                const input = card.querySelector(`input[name="${field}"]`);
                if (input) {
                    input.value = input.defaultValue;
                    input.classList.remove('is-saving');
                }
            }
        }).catch(error => {
            showMessage('更新失败，请稍后重试', 'error');
            // 恢复原值
            const input = card.querySelector(`input[name="${field}"]`);
            if (input) {
                input.value = input.defaultValue;
                input.classList.remove('is-saving');
            }
        });
    }

    function showMessage(message, type) {
        const resultDiv = document.getElementById('result-message');
        resultDiv.innerHTML = `<div class="result-message ${type}">${message}</div>`;

        setTimeout(() => {
            resultDiv.innerHTML = '';
        }, 3000);
    }

    function showAddConfigModal(assessmentType, assessmentName) {
        const configName = prompt(`请输入新的${assessmentName}配置名称：`);
        if (!configName || configName.trim() === '') {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'add_requirement_config');
        formData.append('assessment_type', assessmentType);
        formData.append('config_name', configName.trim());

        fetch('staff_assessment.php', {
            method: 'POST',
            body: formData
        }).then(r => r.json()).then(res => {
            if (res.success) {
                showMessage(res.message, 'success');
                // 刷新页面以显示新配置
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showMessage(res.message, 'error');
            }
        }).catch(error => {
            showMessage('添加配置失败，请稍后重试', 'error');
        });
    }

    function deleteConfig(requirementId, configName) {
        if (!confirm(`确定要删除配置"${configName}"吗？`)) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_requirement_config');
        formData.append('requirement_id', requirementId);

        fetch('staff_assessment.php', {
            method: 'POST',
            body: formData
        }).then(r => r.json()).then(res => {
            if (res.success) {
                showMessage(res.message, 'success');
                // 刷新页面以更新配置列表
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showMessage(res.message, 'error');
            }
        }).catch(error => {
            showMessage('删除配置失败，请稍后重试', 'error');
        });
    }

    function toggleConfigSummary(requirementId, type) {
        const summaryDiv = document.getElementById(`config-summary-${requirementId}`);
        const btn = event.target;

        if (summaryDiv.style.display === 'none' || summaryDiv.style.display === '') {
            summaryDiv.style.display = 'block';
            btn.textContent = '📋 收起配置总结';
        } else {
            summaryDiv.style.display = 'none';
            btn.textContent = '📋 查看配置总结';
        }
    }

    // 回车键搜索
    document.getElementById('search-term').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchUser();
        }
    });

    // 页面加载完成后自动显示Uploader+用户
    document.addEventListener('DOMContentLoaded', function() {
        loadUploaderUsers();
    });
</script>

<?php
stdfoot();
?>
<style id="codex-assessment-polish">
.assessment-container{max-width:1440px;margin:24px auto;padding:0 20px;color:#243447}.header-section{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:20px 22px;background:#eef5ff;border:1px solid #dce9f7;border-radius:14px;margin-bottom:18px}.assessment-summary{display:grid!important;grid-template-columns:repeat(4,minmax(150px,1fr));gap:12px;margin:18px 0}.assessment-summary-item{background:#fff;border:1px solid #e4ecf4;border-radius:10px;padding:16px;box-shadow:0 4px 16px rgba(30,65,100,.06)}.section{background:#fff;border:1px solid #e4ecf4;border-radius:12px;padding:18px;margin:18px 0;box-shadow:0 4px 16px rgba(30,65,100,.05)}.assessment-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:12px}.assessment-item{border:1px solid #e5edf5!important;border-radius:10px!important;padding:14px!important;background:#fbfdff!important}.requirement-card{border:1px solid #dfe9f2!important;border-radius:12px!important;padding:18px!important;margin:14px 0!important;background:#fff!important}.card-header{display:flex;align-items:center;justify-content:space-between;gap:10px;border-bottom:1px solid #edf2f7;padding-bottom:12px;margin-bottom:14px}.config-item{display:grid!important;grid-template-columns:minmax(180px,1fr) minmax(150px,220px);align-items:center;gap:12px;padding:10px 0!important;border-bottom:1px solid #f0f3f7}.config-item:last-child{border-bottom:0}.config-item input,.config-item select{width:100%;min-height:38px;border:1px solid #cfdbe7;border-radius:7px;padding:7px 10px;background:#fff}.config-summary{margin-top:14px;padding:12px 14px;background:#f5f9fd;border:1px dashed #b6cbe0;border-radius:8px;color:#47617a}@media(max-width:850px){.assessment-container{padding:0 12px;margin:14px auto}.assessment-summary{grid-template-columns:repeat(2,minmax(130px,1fr))}.config-item{grid-template-columns:1fr}.section,.requirement-card{padding:14px!important}}@media(max-width:480px){.assessment-summary{grid-template-columns:1fr}.header-section{padding:16px}}</style>