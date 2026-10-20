<?php

/**
 * NovaHD custom role and direct permission extension.
 * Independently implemented against NexusPHP's public permission filters.
 */

function nova_permission_can_manage(): bool
{
    return get_user_class() >= UC_ADMINISTRATOR;
}

function nova_permission_token(int $userId): string
{
    return hash_hmac('sha256', 'nova-permission|' . $userId, (string)config('app.key'));
}

function nova_permission_verify_token(string $token, int $userId): bool
{
    return $token !== '' && hash_equals(nova_permission_token($userId), $token);
}

function nova_permission_role_filter(array $permissions, int $userId): array
{
    $userId = intval($userId);
    if ($userId <= 0) {
        return $permissions;
    }
    try {
        $result = sql_query("SELECT DISTINCT rp.permission
            FROM nova_permission_user_roles ur
            INNER JOIN nova_permission_roles r ON r.id = ur.role_id AND r.enabled = 'yes'
            INNER JOIN nova_permission_role_permissions rp ON rp.role_id = r.id
            WHERE ur.user_id = {$userId} AND (ur.expires_at IS NULL OR ur.expires_at > NOW())");
        while ($result && ($row = mysql_fetch_assoc($result))) {
            $permissions[] = $row['permission'];
        }
    } catch (Throwable $e) {
        do_log('[NOVA_PERMISSION_ROLE_FILTER] ' . $e->getMessage(), 'error');
    }
    return array_values(array_unique($permissions));
}

function nova_permission_direct_filter(array $permissions, int $userId): array
{
    $userId = intval($userId);
    if ($userId <= 0) {
        return $permissions;
    }
    try {
        $result = sql_query("SELECT permission FROM nova_permission_user_permissions
            WHERE user_id = {$userId} AND (expires_at IS NULL OR expires_at > NOW())");
        while ($result && ($row = mysql_fetch_assoc($result))) {
            $permissions[] = $row['permission'];
        }
    } catch (Throwable $e) {
        do_log('[NOVA_PERMISSION_DIRECT_FILTER] ' . $e->getMessage(), 'error');
    }
    return array_values(array_unique($permissions));
}

function nova_permission_labels(): array
{
    return [
        'torrent-approval' => '审核种子',
        'torrent-approval-allow-automatic' => '自动通过审核',
        'torrentmanage' => '管理种子',
        'torrent-delete' => '删除种子',
        'torrentsticky' => '设置种子置顶',
        'torrentonpromotion' => '设置种子优惠',
        'torrent_hr' => '设置 H&R',
        'torrent-set-price' => '设置种子价格',
        'torrent-set-special-tag' => '设置特殊标签',
        'movetorrent' => '移动种子分类',
        'seebanned' => '查看被禁种子',
        'viewanonymous' => '查看匿名用户',
        'beanonymous' => '匿名发布',
        'upload' => '发布普通种子',
        'uploadspecial' => '发布特殊种子',
        'view_special_torrent' => '查看特殊种子',
        'askreseed' => '请求续种',
        'torrentstructure' => '查看种子结构',
        'uploadsub' => '上传字幕',
        'submanage' => '管理字幕',
        'commanage' => '管理评论',
    ];
}

function nova_permission_available(): array
{
    $permissions = nova_permission_labels();
    $authority = get_setting('authority');
    if (is_array($authority)) {
        foreach (array_keys($authority) as $permission) {
            if (!in_array($permission, ['defaultclass', 'staffmem'], true) && preg_match('/^[a-z0-9_-]{2,64}$/i', $permission)) {
                $permissions[$permission] = $permissions[$permission] ?? $permission;
            }
        }
    }
    ksort($permissions);
    return $permissions;
}

function nova_permission_normalize(array $selected, string $custom = ''): array
{
    $items = array_merge($selected, preg_split('/[\s,，;；]+/u', $custom, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    $result = [];
    foreach ($items as $permission) {
        $permission = strtolower(trim((string)$permission));
        if (!preg_match('/^[a-z0-9_-]{2,64}$/', $permission)) {
            throw new InvalidArgumentException('权限标识格式无效：' . htmlspecialchars($permission));
        }
        $result[$permission] = $permission;
    }
    return array_values($result);
}

function nova_permission_find_user(string $username): array
{
    $usernameEscaped = sqlesc(trim($username));
    $result = sql_query("SELECT id, username, class FROM users WHERE username = {$usernameEscaped} LIMIT 1");
    $user = $result ? mysql_fetch_assoc($result) : null;
    if (!$user) {
        throw new RuntimeException('未找到用户：' . htmlspecialchars($username));
    }
    return $user;
}

function nova_permission_expiry(int $days): string
{
    return $days > 0 ? sqlesc(date('Y-m-d H:i:s', time() + min($days, 3650) * 86400)) : 'NULL';
}

function nova_permission_audit(int $operatorId, string $action, string $targetType, int $targetId, array $details = []): void
{
    sql_query("INSERT INTO nova_permission_audit_logs
        (operator_id, action, target_type, target_id, details, created_at)
        VALUES ({$operatorId}, " . sqlesc($action) . ", " . sqlesc($targetType) . ", {$targetId}, " . sqlesc(json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . ", NOW())");
}

function nova_permission_save_role(int $operatorId, string $name, string $description, string $color, array $permissions): int
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name, 'UTF-8') > 40) {
        throw new InvalidArgumentException('角色名称长度必须为 1 至 40 个字符。');
    }
    if (!preg_match('/^#[0-9a-f]{6}$/i', $color)) {
        $color = '#0f9f83';
    }
    if (!$permissions) {
        throw new InvalidArgumentException('请至少选择一个权限。');
    }
    sql_query('START TRANSACTION');
    try {
        $existingResult = sql_query('SELECT id FROM nova_permission_roles WHERE name = ' . sqlesc($name) . ' FOR UPDATE');
        $existing = $existingResult ? mysql_fetch_assoc($existingResult) : null;
        if ($existing) {
            $roleId = (int)$existing['id'];
            sql_query("UPDATE nova_permission_roles SET description = " . sqlesc(trim($description)) . ", color = " . sqlesc($color) . ", enabled = 'yes', updated_by = {$operatorId}, updated_at = NOW() WHERE id = {$roleId}");
            sql_query("DELETE FROM nova_permission_role_permissions WHERE role_id = {$roleId}");
            $action = 'update_role';
        } else {
            sql_query("INSERT INTO nova_permission_roles (name, description, color, enabled, created_by, updated_by, created_at, updated_at)
                VALUES (" . sqlesc($name) . ", " . sqlesc(trim($description)) . ", " . sqlesc($color) . ", 'yes', {$operatorId}, {$operatorId}, NOW(), NOW())") or throw new RuntimeException('无法创建角色。');
            $roleId = (int)mysql_insert_id();
            $action = 'create_role';
        }
        foreach ($permissions as $permission) {
            sql_query("INSERT INTO nova_permission_role_permissions (role_id, permission, created_at) VALUES ({$roleId}, " . sqlesc($permission) . ", NOW())");
        }
        nova_permission_audit($operatorId, $action, 'role', $roleId, ['name' => $name, 'permissions' => $permissions]);
        sql_query('COMMIT');
        return $roleId;
    } catch (Throwable $e) {
        sql_query('ROLLBACK');
        throw $e;
    }
}

function nova_permission_delete_role(int $operatorId, int $roleId): void
{
    sql_query('START TRANSACTION');
    try {
        $result = sql_query("SELECT name FROM nova_permission_roles WHERE id = {$roleId} FOR UPDATE");
        $role = $result ? mysql_fetch_assoc($result) : null;
        if (!$role) {
            throw new RuntimeException('角色不存在。');
        }
        nova_permission_audit($operatorId, 'delete_role', 'role', $roleId, ['name' => $role['name']]);
        sql_query("DELETE FROM nova_permission_roles WHERE id = {$roleId}");
        sql_query('COMMIT');
    } catch (Throwable $e) {
        sql_query('ROLLBACK');
        throw $e;
    }
}

function nova_permission_assign_role(int $operatorId, int $userId, int $roleId, int $days): void
{
    $roleResult = sql_query("SELECT id, name FROM nova_permission_roles WHERE id = {$roleId} AND enabled = 'yes' LIMIT 1");
    $role = $roleResult ? mysql_fetch_assoc($roleResult) : null;
    if (!$role) {
        throw new RuntimeException('角色不存在或已停用。');
    }
    $expiresAt = nova_permission_expiry($days);
    sql_query("INSERT INTO nova_permission_user_roles (user_id, role_id, assigned_by, expires_at, created_at, updated_at)
        VALUES ({$userId}, {$roleId}, {$operatorId}, {$expiresAt}, NOW(), NOW())
        ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), expires_at = VALUES(expires_at), updated_at = NOW()") or throw new RuntimeException('无法分配角色。');
    nova_permission_audit($operatorId, 'assign_role', 'user', $userId, ['role_id' => $roleId, 'role' => $role['name'], 'days' => $days]);
    clear_user_cache($userId);
}

function nova_permission_revoke_role(int $operatorId, int $assignmentId): int
{
    $result = sql_query("SELECT user_id, role_id FROM nova_permission_user_roles WHERE id = {$assignmentId} LIMIT 1");
    $assignment = $result ? mysql_fetch_assoc($result) : null;
    if (!$assignment) {
        throw new RuntimeException('角色分配记录不存在。');
    }
    sql_query("DELETE FROM nova_permission_user_roles WHERE id = {$assignmentId}");
    nova_permission_audit($operatorId, 'revoke_role', 'user', (int)$assignment['user_id'], ['role_id' => (int)$assignment['role_id']]);
    clear_user_cache((int)$assignment['user_id']);
    return (int)$assignment['user_id'];
}

function nova_permission_grant_direct(int $operatorId, int $userId, array $permissions, int $days): void
{
    if (!$permissions) {
        throw new InvalidArgumentException('请至少填写一个直接权限。');
    }
    $expiresAt = nova_permission_expiry($days);
    foreach ($permissions as $permission) {
        sql_query("INSERT INTO nova_permission_user_permissions (user_id, permission, assigned_by, expires_at, created_at, updated_at)
            VALUES ({$userId}, " . sqlesc($permission) . ", {$operatorId}, {$expiresAt}, NOW(), NOW())
            ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), expires_at = VALUES(expires_at), updated_at = NOW()") or throw new RuntimeException('无法授予直接权限。');
    }
    nova_permission_audit($operatorId, 'grant_direct', 'user', $userId, ['permissions' => $permissions, 'days' => $days]);
    clear_user_cache($userId);
}

function nova_permission_revoke_direct(int $operatorId, int $grantId): int
{
    $result = sql_query("SELECT user_id, permission FROM nova_permission_user_permissions WHERE id = {$grantId} LIMIT 1");
    $grant = $result ? mysql_fetch_assoc($result) : null;
    if (!$grant) {
        throw new RuntimeException('直接权限记录不存在。');
    }
    sql_query("DELETE FROM nova_permission_user_permissions WHERE id = {$grantId}");
    nova_permission_audit($operatorId, 'revoke_direct', 'user', (int)$grant['user_id'], ['permission' => $grant['permission']]);
    clear_user_cache((int)$grant['user_id']);
    return (int)$grant['user_id'];
}

function nova_permission_roles(): array
{
    $roles = [];
    $result = sql_query("SELECT r.*,
        (SELECT COUNT(*) FROM nova_permission_user_roles ur WHERE ur.role_id = r.id AND (ur.expires_at IS NULL OR ur.expires_at > NOW())) AS user_count
        FROM nova_permission_roles r ORDER BY r.id ASC");
    while ($result && ($row = mysql_fetch_assoc($result))) {
        $permissions = [];
        $permissionResult = sql_query("SELECT permission FROM nova_permission_role_permissions WHERE role_id = " . intval($row['id']) . " ORDER BY permission");
        while ($permissionResult && ($permission = mysql_fetch_assoc($permissionResult))) {
            $permissions[] = $permission['permission'];
        }
        $row['permissions'] = $permissions;
        $roles[] = $row;
    }
    return $roles;
}

function nova_permission_user_assignments(int $userId): array
{
    $roles = [];
    $result = sql_query("SELECT ur.id, ur.role_id, ur.expires_at, r.name, r.color
        FROM nova_permission_user_roles ur INNER JOIN nova_permission_roles r ON r.id = ur.role_id
        WHERE ur.user_id = {$userId} ORDER BY r.name");
    while ($result && ($row = mysql_fetch_assoc($result))) {
        $roles[] = $row;
    }
    $direct = [];
    $result = sql_query("SELECT id, permission, expires_at FROM nova_permission_user_permissions WHERE user_id = {$userId} ORDER BY permission");
    while ($result && ($row = mysql_fetch_assoc($result))) {
        $direct[] = $row;
    }
    return ['roles' => $roles, 'direct' => $direct];
}

function nova_permission_label(string $permission): string
{
    return nova_permission_labels()[$permission] ?? $permission;
}

function nova_permission_render_detail(array $torrent, array $currentUser): void
{
    if (!nova_permission_can_manage()) {
        return;
    }
    $torrentId = (int)$torrent['id'];
    $operatorId = (int)$currentUser['id'];
    $selectedUid = intval($_GET['permission_uid'] ?? $torrent['owner']);
    if (!empty($_GET['permission_username'])) {
        $selectedResult = sql_query('SELECT id, username, class FROM users WHERE username = ' . sqlesc(trim((string)$_GET['permission_username'])) . ' LIMIT 1');
        $lookupUser = $selectedResult ? mysql_fetch_assoc($selectedResult) : null;
        if ($lookupUser) {
            $selectedUid = (int)$lookupUser['id'];
        }
    }
    $selectedResult = sql_query("SELECT id, username, class FROM users WHERE id = {$selectedUid} LIMIT 1");
    $selectedUser = $selectedResult ? mysql_fetch_assoc($selectedResult) : null;
    if (!$selectedUser) {
        $selectedUid = (int)$torrent['owner'];
        $selectedResult = sql_query("SELECT id, username, class FROM users WHERE id = {$selectedUid} LIMIT 1");
        $selectedUser = $selectedResult ? mysql_fetch_assoc($selectedResult) : ['id' => 0, 'username' => '未知用户', 'class' => 0];
    }
    $roles = nova_permission_roles();
    $assignments = nova_permission_user_assignments($selectedUid);
    $available = nova_permission_available();
    $success = isset($_GET['nova_permission_success']);
    $token = nova_permission_token($operatorId);
    ?>
    <tr class="nova-permission-detail-row"><td colspan="2" style="padding:0;border:0;background:transparent">
    <style>
    .nova-permission-panel{width:65%;min-width:920px;margin:16px auto 20px;border:1px solid rgba(13,148,136,.36);border-radius:8px;overflow:hidden;background:linear-gradient(135deg,rgba(240,253,250,.98),rgba(255,247,237,.97));color:#17333b;box-shadow:0 12px 28px rgba(13,78,74,.12);box-sizing:border-box}.nova-permission-head{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:linear-gradient(90deg,rgba(20,184,166,.16),rgba(255,255,255,.8),rgba(249,115,22,.10));border-bottom:1px solid rgba(13,148,136,.2)}.nova-permission-head strong{font-size:16px;color:#0f766e}.nova-permission-lock{padding:3px 8px;border-radius:999px;background:#fff7ed;color:#c2410c;font-size:11px;font-weight:800}.nova-permission-success{padding:8px 14px;background:rgba(16,185,129,.12);color:#047857;border-bottom:1px solid rgba(16,185,129,.2)}.nova-permission-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding:12px 16px}.nova-permission-stat{padding:9px 11px;border:1px solid rgba(13,148,136,.14);border-radius:6px;background:rgba(255,255,255,.75)}.nova-permission-stat span{display:block;color:#64748b;font-size:11px}.nova-permission-stat b{display:block;margin-top:3px;color:#17333b;font-size:14px}.nova-permission-manage{margin:0 16px 15px;border:0}.nova-permission-manage>summary{display:flex!important;align-items:center;gap:10px;width:100%;box-sizing:border-box;margin:0 0 12px!important;padding:10px 12px!important;border:1px solid rgba(13,148,136,.20);border-radius:6px;background:linear-gradient(90deg,rgba(204,251,241,.72),rgba(255,255,255,.86),rgba(255,237,213,.48));color:#0f766e!important;cursor:pointer;list-style:none;transition:border-color .18s ease,box-shadow .18s ease,background .18s ease}.nova-permission-manage>summary::-webkit-details-marker{display:none}.nova-permission-manage>summary::before{content:'›';display:grid;place-items:center;flex:0 0 24px;width:24px;height:24px;border-radius:5px;background:#0f9f83;color:#fff;font-size:20px;font-weight:500;line-height:1;transform:rotate(0deg);transition:transform .18s ease,background .18s ease}.nova-permission-manage>summary::after{display:none!important;content:none!important}.nova-permission-summary-title{display:flex;flex-direction:column;gap:1px;min-width:0}.nova-permission-summary-title b{color:#0f766e;font-size:12px}.nova-permission-summary-title small{color:#64748b;font-size:10px;font-weight:500}.nova-permission-summary-state{margin-left:auto;padding:3px 7px;border-radius:999px;background:#fff;color:#64748b;font-size:10px;font-weight:700}.nova-permission-manage>summary:hover{border-color:rgba(13,148,136,.42);box-shadow:0 4px 12px rgba(13,78,74,.08)}.nova-permission-manage[open]>summary{border-color:rgba(13,148,136,.32);background:linear-gradient(90deg,rgba(153,246,228,.42),rgba(255,255,255,.9),rgba(254,215,170,.38))}.nova-permission-manage[open]>summary::before{transform:rotate(90deg);background:#f97316}.nova-permission-manage[open] .nova-permission-summary-state{color:#047857;background:#ecfdf5}.nova-permission-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.nova-permission-section{padding:12px;border:1px solid rgba(13,148,136,.14);border-radius:6px;background:rgba(255,255,255,.7)}.nova-permission-section h3{margin:0 0 10px!important;font-size:13px!important;color:#0f766e!important}.nova-permission-form{display:grid;gap:8px}.nova-permission-row{display:grid;grid-template-columns:1fr 1fr;gap:8px}.nova-permission-form input,.nova-permission-form select{height:32px;box-sizing:border-box;border:1px solid rgba(13,148,136,.3);border-radius:5px;background:#fff;color:#17333b;padding:0 8px}.nova-permission-form button,.nova-permission-mini button{height:32px;border:0;border-radius:5px;background:#0f9f83;color:#fff;font-weight:800;padding:0 13px;cursor:pointer}.nova-permission-checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:5px;max-height:180px;overflow:auto;padding:8px;border:1px solid rgba(13,148,136,.12);border-radius:5px;background:#fff}.nova-permission-checks label{display:flex;align-items:center;gap:5px;font-size:11px;color:#475569}.nova-permission-checks input{width:auto;height:auto}.nova-permission-list{margin-top:10px;border-top:1px solid rgba(13,148,136,.12)}.nova-permission-item{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;padding:8px 0;border-bottom:1px solid rgba(13,148,136,.1)}.nova-permission-item-main{min-width:0}.nova-permission-item-main b{font-size:12px}.nova-permission-tags{display:flex;gap:4px;flex-wrap:wrap;margin-top:5px}.nova-permission-tag{padding:2px 5px;border-radius:4px;background:#e6fffa;color:#0f766e;font-size:10px}.nova-permission-mini{display:inline}.nova-permission-mini button{height:25px;padding:0 8px;background:#fff1f2;color:#be123c;border:1px solid #fecdd3}.nova-permission-target{display:flex;align-items:center;gap:7px;margin-bottom:10px;padding:8px 10px;border-radius:5px;background:#ecfdf5;color:#166534}.nova-permission-expired{opacity:.55}.nova-permission-note{color:#64748b;font-size:11px;line-height:1.5}.nova-permission-custom{width:100%}@media(max-width:1200px){.nova-permission-panel{width:92%;min-width:0}}@media(max-width:700px){.nova-permission-panel{width:97%}.nova-permission-grid,.nova-permission-row{grid-template-columns:1fr}.nova-permission-summary{grid-template-columns:1fr}.nova-permission-checks{grid-template-columns:1fr}}
    </style>
    <section class="nova-permission-panel" id="nova-permission-panel">
      <?php if ($success): ?><div class="nova-permission-success">权限设置已保存。</div><?php endif; ?>
      <div class="nova-permission-summary">
        <div class="nova-permission-stat"><span>当前角色数</span><b><?php echo count($roles); ?></b></div>
        <div class="nova-permission-stat"><span>当前管理用户</span><b><?php echo get_username($selectedUid); ?></b></div>
        <div class="nova-permission-stat"><span>扩展授权</span><b><?php echo count($assignments['roles']); ?> 个角色 / <?php echo count($assignments['direct']); ?> 个直接权限</b></div>
      </div>
      <details class="nova-permission-manage"<?php echo isset($_GET['permission_uid']) || isset($_GET['permission_username']) ? ' open' : ''; ?>><summary><span class="nova-permission-summary-title"><b>权限管理</b><small>创建角色、分配角色与直接权限</small></span><span class="nova-permission-summary-state">点击展开 / 收起</span></summary>
        <div class="nova-permission-grid">
          <section class="nova-permission-section"><h3>创建或更新角色</h3>
            <form class="nova-permission-form" method="post" action="nova-permission-action.php">
              <input type="hidden" name="action" value="save_role"><input type="hidden" name="torrent_id" value="<?php echo $torrentId; ?>"><input type="hidden" name="token" value="<?php echo $token; ?>">
              <div class="nova-permission-row"><input type="text" name="name" maxlength="40" placeholder="角色名称，例如：审核员" required><input type="color" name="color" value="#0f9f83" title="角色颜色"></div>
              <input type="text" name="description" maxlength="160" placeholder="角色说明">
              <div class="nova-permission-checks"><?php foreach ($available as $permission => $label): ?><label><input type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($permission); ?>"> <?php echo htmlspecialchars($label); ?></label><?php endforeach; ?></div>
              <input class="nova-permission-custom" type="text" name="custom_permissions" placeholder="其他权限标识，使用逗号分隔">
              <button type="submit">保存角色</button>
            </form>
            <div class="nova-permission-list"><?php if (!$roles): ?><p class="nova-permission-note">尚未创建自定义角色。</p><?php endif; ?><?php foreach ($roles as $role): ?>
              <div class="nova-permission-item"><div class="nova-permission-item-main"><b style="color:<?php echo htmlspecialchars($role['color']); ?>"><?php echo htmlspecialchars($role['name']); ?></b> <small><?php echo intval($role['user_count']); ?> 人</small><div class="nova-permission-tags"><?php foreach ($role['permissions'] as $permission): ?><span class="nova-permission-tag" title="<?php echo htmlspecialchars($permission); ?>"><?php echo htmlspecialchars(nova_permission_label($permission)); ?></span><?php endforeach; ?></div></div><form class="nova-permission-mini" method="post" action="nova-permission-action.php" onsubmit="return confirm('确定删除此角色及其全部分配吗？')"><input type="hidden" name="action" value="delete_role"><input type="hidden" name="role_id" value="<?php echo intval($role['id']); ?>"><input type="hidden" name="torrent_id" value="<?php echo $torrentId; ?>"><input type="hidden" name="token" value="<?php echo $token; ?>"><button type="submit">删除</button></form></div>
            <?php endforeach; ?></div>
          </section>
          <section class="nova-permission-section"><h3>用户授权</h3>
            <form class="nova-permission-form" method="get" action="details.php"><input type="hidden" name="id" value="<?php echo $torrentId; ?>"><div class="nova-permission-row"><input type="text" name="permission_username" value="<?php echo htmlspecialchars($selectedUser['username']); ?>" placeholder="输入用户名"><button type="submit" name="permission_lookup" value="1">载入用户</button></div></form>
            <div class="nova-permission-target">当前：<?php echo get_username($selectedUid); ?> · <?php echo get_user_class_name((int)$selectedUser['class']); ?></div>
            <?php if ($roles): ?><form class="nova-permission-form" method="post" action="nova-permission-action.php"><input type="hidden" name="action" value="assign_role"><input type="hidden" name="torrent_id" value="<?php echo $torrentId; ?>"><input type="hidden" name="token" value="<?php echo $token; ?>"><input type="hidden" name="username" value="<?php echo htmlspecialchars($selectedUser['username']); ?>"><div class="nova-permission-row"><select name="role_id"><?php foreach ($roles as $role): ?><option value="<?php echo intval($role['id']); ?>"><?php echo htmlspecialchars($role['name']); ?></option><?php endforeach; ?></select><input type="number" name="days" min="0" max="3650" value="0" placeholder="有效天数，0 为永久"></div><button type="submit">分配角色</button></form><?php endif; ?>
            <form class="nova-permission-form" method="post" action="nova-permission-action.php" style="margin-top:10px"><input type="hidden" name="action" value="grant_direct"><input type="hidden" name="torrent_id" value="<?php echo $torrentId; ?>"><input type="hidden" name="token" value="<?php echo $token; ?>"><input type="hidden" name="username" value="<?php echo htmlspecialchars($selectedUser['username']); ?>"><div class="nova-permission-row"><select name="permissions[]"><?php foreach ($available as $permission => $label): ?><option value="<?php echo htmlspecialchars($permission); ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select><input type="number" name="days" min="0" max="3650" value="0" placeholder="有效天数，0 为永久"></div><input type="text" name="custom_permissions" placeholder="可填写其他权限标识，逗号分隔"><button type="submit">授予直接权限</button></form>
            <div class="nova-permission-list"><h3 style="margin-top:10px!important">已分配角色</h3><?php if (!$assignments['roles']): ?><p class="nova-permission-note">暂无角色。</p><?php endif; ?><?php foreach ($assignments['roles'] as $assignment): $expired = $assignment['expires_at'] && strtotime($assignment['expires_at']) <= time(); ?><div class="nova-permission-item<?php echo $expired ? ' nova-permission-expired' : ''; ?>"><div class="nova-permission-item-main"><b style="color:<?php echo htmlspecialchars($assignment['color']); ?>"><?php echo htmlspecialchars($assignment['name']); ?></b><div class="nova-permission-note"><?php echo $assignment['expires_at'] ? '到期：' . htmlspecialchars($assignment['expires_at']) : '永久有效'; ?></div></div><form class="nova-permission-mini" method="post" action="nova-permission-action.php"><input type="hidden" name="action" value="revoke_role"><input type="hidden" name="assignment_id" value="<?php echo intval($assignment['id']); ?>"><input type="hidden" name="torrent_id" value="<?php echo $torrentId; ?>"><input type="hidden" name="token" value="<?php echo $token; ?>"><button type="submit">撤销</button></form></div><?php endforeach; ?>
              <h3 style="margin-top:12px!important">直接权限</h3><?php if (!$assignments['direct']): ?><p class="nova-permission-note">暂无直接权限。</p><?php endif; ?><?php foreach ($assignments['direct'] as $grant): $expired = $grant['expires_at'] && strtotime($grant['expires_at']) <= time(); ?><div class="nova-permission-item<?php echo $expired ? ' nova-permission-expired' : ''; ?>"><div class="nova-permission-item-main"><b><?php echo htmlspecialchars(nova_permission_label($grant['permission'])); ?></b><div class="nova-permission-note"><?php echo htmlspecialchars($grant['permission']); ?> · <?php echo $grant['expires_at'] ? '到期：' . htmlspecialchars($grant['expires_at']) : '永久有效'; ?></div></div><form class="nova-permission-mini" method="post" action="nova-permission-action.php"><input type="hidden" name="action" value="revoke_direct"><input type="hidden" name="grant_id" value="<?php echo intval($grant['id']); ?>"><input type="hidden" name="torrent_id" value="<?php echo $torrentId; ?>"><input type="hidden" name="token" value="<?php echo $token; ?>"><button type="submit">撤销</button></form></div><?php endforeach; ?></div>
          </section>
        </div>
        <p class="nova-permission-note" style="margin-top:10px">最终权限由等级权限、角色权限和直接权限合并。有效期到达后扩展权限自动失效。</p>
      </details>
    </section></td></tr>
    <?php
}

add_filter('user_role_permissions', 'nova_permission_role_filter', 10, 2);
add_filter('user_direct_permissions', 'nova_permission_direct_filter', 10, 2);
