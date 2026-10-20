<?php

/**
 * NovaHD standalone navigation renderer and configuration.
 */

function nova_navigation_is_admin(): bool
{
    return get_user_class() >= UC_ADMINISTRATOR;
}

function nova_navigation_token(int $userId): string
{
    return hash_hmac('sha256', $userId . '|nova-navigation|' . date('Y-m-d'), (string)config('app.key'));
}

function nova_navigation_verify_token(string $token, int $userId): bool
{
    return $token !== '' && hash_equals(nova_navigation_token($userId), $token);
}

function nova_navigation_rows(): array
{
    $result = sql_query('SELECT * FROM navigations ORDER BY parent_id ASC, sort_order ASC, id ASC');
    $rows = [];
    while ($result && ($row = mysql_fetch_assoc($result))) {
        $rows[] = $row;
    }
    return $rows;
}

function nova_navigation_can_view(?string $permission): bool
{
    $permission = trim((string)$permission);
    if ($permission === '') {
        return true;
    }
    if (ctype_digit($permission)) {
        return get_user_class() >= (int)$permission;
    }
    try {
        return user_can($permission);
    } catch (Throwable) {
        return false;
    }
}

function nova_navigation_current_url_matches(string $url): bool
{
    if ($url === '' || $url === '#') {
        return false;
    }
    $decodedUrl = html_entity_decode($url);
    $path = parse_url($decodedUrl, PHP_URL_PATH) ?: '';
    $current = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($current === '' || basename($path) !== $current) {
        return false;
    }

    $queryString = (string)(parse_url($decodedUrl, PHP_URL_QUERY) ?? '');
    if ($queryString === '') {
        return true;
    }
    parse_str($queryString, $targetQuery);
    $currentQuery = $_GET;
    $identifiers = [];
    foreach ($targetQuery as $key => $value) {
        if ($key === 'view' || $key === 'cat' || preg_match('/^(cat|team|standard)\d+$/', (string)$key)) {
            $identifiers[$key] = (string)$value;
        }
    }
    if (!$identifiers) {
        $identifiers = array_filter($targetQuery, static fn ($value) => (string)$value !== '');
    }
    foreach ($identifiers as $key => $value) {
        if ($key === 'cat' && (string)$value === '411' && !isset($currentQuery['cat']) && (string)($currentQuery['cat411'] ?? '') === '1') {
            continue;
        }
        if (!array_key_exists($key, $currentQuery) || (string)$currentQuery[$key] !== (string)$value) {
            return false;
        }
    }
    return true;
}

function nova_navigation_label(string $label): string
{
    return str_replace(' ', '&nbsp;', htmlspecialchars($label, ENT_QUOTES, 'UTF-8'));
}

function nova_navigation_link(array $row, bool $selected = false): string
{
    $url = trim((string)$row['url']);
    $href = $url !== '' ? htmlspecialchars($url, ENT_QUOTES, 'UTF-8') : '#';
    $target = (int)$row['new_tab'] === 1 ? ' target="_blank" rel="noopener"' : '';
    $class = $selected ? ' class="selected"' : '';
    return '<a href="' . $href . '"' . $target . $class . '>' . nova_navigation_label((string)$row['name']) . '</a>';
}

function nova_navigation_render(): string
{
    $rows = nova_navigation_rows();
    $roots = [];
    $children = [];
    foreach ($rows as $row) {
        if ((int)$row['parent_id'] === 0) {
            $roots[] = $row;
        } else {
            $children[(int)$row['parent_id']][] = $row;
        }
    }

    if (function_exists("nova_permission_can_manage") && nova_permission_can_manage()) {
        $roots[] = array("id" => -99, "name" => "管理插件", "url" => "nova-plugin-manager.php", "new_tab" => 0);
    }
    $html = '<style>
      #nav{overflow:visible!important}
      #nav .nova-navigation.menu{display:flex!important;align-items:center!important;justify-content:center!important;gap:8px!important;box-sizing:border-box!important;width:100%!important;min-height:64px!important;margin:0!important;padding:10px 14px!important;overflow:visible!important;list-style:none!important;background:linear-gradient(100deg,#0c2d39 0%,#10504f 56%,#137f72 100%)!important;border:1px solid rgba(126,205,194,.34)!important;border-radius:8px!important;box-shadow:0 5px 16px rgba(19,62,67,.16)!important;white-space:nowrap!important}
      #nav .nova-navigation>li{position:relative!important;display:block!important;flex:0 0 auto!important;margin:0!important;padding:0!important;background:transparent!important}
      #nav .nova-navigation>li>a{position:relative!important;display:flex!important;align-items:center!important;justify-content:center!important;box-sizing:border-box!important;min-width:64px!important;height:40px!important;margin:0!important;padding:0 14px!important;border:1px solid rgba(221,250,245,.36)!important;border-radius:10px!important;background:rgba(255,255,255,.07)!important;color:#f7fffe!important;-webkit-text-fill-color:#f7fffe!important;font-size:13px!important;font-weight:750!important;line-height:1!important;text-decoration:none!important;letter-spacing:.4px!important;white-space:nowrap!important;text-shadow:0 1px 2px rgba(0,24,29,.28)!important;box-shadow:0 3px 8px rgba(0,25,30,.12)!important;transition:all .18s ease!important}
      #nav .nova-navigation>li>a:after{display:none!important;content:none!important}
      #nav .nova-navigation>li:hover>a{color:#fff!important;-webkit-text-fill-color:#fff!important;background:rgba(255,255,255,.17)!important;border-color:rgba(221,250,245,.72)!important;transform:translateY(-2px)!important;box-shadow:0 6px 13px rgba(0,25,30,.2)!important}
      #nav .nova-navigation>li>a.selected{color:#123f45!important;-webkit-text-fill-color:#123f45!important;background:#e9fffa!important;border-color:#bdf4e8!important;font-weight:800!important;box-shadow:0 6px 14px rgba(0,25,30,.22)!important}
      #nav .nova-navigation .sub-menu{display:none!important;position:absolute!important;z-index:10020!important;top:calc(100% - 1px)!important;left:50%!important;transform:translateX(-50%)!important;box-sizing:border-box!important;min-width:190px!important;margin:0!important;padding:7px!important;list-style:none!important;background:#fff!important;border:1px solid #d4e7e4!important;border-radius:7px!important;box-shadow:0 12px 30px rgba(25,67,69,.18)!important;white-space:nowrap!important}
      #nav .nova-navigation li.has-children:hover>.sub-menu,#nav .nova-navigation li.has-children:focus-within>.sub-menu{display:block!important}
      #nav .nova-navigation .sub-menu li{display:block!important;margin:0!important;padding:0!important;background:#fff!important}
      #nav .nova-navigation .sub-menu a{display:flex!important;align-items:center!important;justify-content:center!important;box-sizing:border-box!important;min-height:38px!important;margin:0!important;padding:8px 13px!important;border:0!important;border-radius:5px!important;background:#fff!important;color:#29484d!important;-webkit-text-fill-color:#29484d!important;font-size:14px!important;font-weight:650!important;line-height:1.35!important;text-align:center!important;text-decoration:none!important;letter-spacing:0!important;white-space:nowrap!important;text-shadow:none!important}
      #nav .nova-navigation .sub-menu a:hover,#nav .nova-navigation .sub-menu a.selected{background:#eaf8f5!important;color:#0b8b76!important;-webkit-text-fill-color:#0b8b76!important}
      #nav .nova-navigation .sub-menu a:before,#nav .nova-navigation .sub-menu a:after{display:none!important;content:none!important}
      @media(max-width:1200px){#nav .nova-navigation.menu{justify-content:flex-start!important;overflow-x:auto!important;overflow-y:visible!important}#nav .nova-navigation>li>a{padding:0 8px!important;font-size:14px!important}}
    @media(max-width:768px){#nav_block,#nav{position:relative!important;z-index:10030!important;overflow:visible!important}#nav_block table,#nav_block td,td.navigation{overflow:visible!important}#nav .nova-navigation li.touch-open>.sub-menu{display:block!important}#nav_block,#nav,#nav .nova-navigation.menu{box-sizing:border-box!important;width:100%!important;max-width:100vw!important;min-width:0!important}#nav{overflow:visible!important}#nav .nova-navigation.menu{display:flex!important;flex-wrap:wrap!important;justify-content:center!important;align-items:center!important;overflow:visible!important;white-space:normal!important;gap:2px!important;padding:6px 4px!important}#nav .nova-navigation>li{flex:0 0 auto!important}#nav .nova-navigation>li>a{height:36px!important;margin:0!important;padding:0 7px!important;font-size:13px!important;line-height:1.1!important}#nav .nova-navigation>li>a:after{left:7px!important;right:7px!important;bottom:1px!important}#nav .nova-navigation .sub-menu{position:absolute!important;z-index:10020!important;max-width:calc(100vw - 16px)!important;white-space:normal!important}#nav .nova-navigation .sub-menu a{min-height:34px!important;padding:7px 10px!important;font-size:13px!important}}</style><ul id="mainmenu" class="menu nova-navigation">';

    foreach ($roots as $root) {
        if (!nova_navigation_can_view(($root['permission'] ?? null))) {
            continue;
        }
        $visibleChildren = array_values(array_filter($children[(int)$root['id']] ?? [], static fn (array $child) => nova_navigation_can_view(($child['permission'] ?? null))));
        if ((int)$root['id'] === 1581 && nova_navigation_is_admin()) {
            $visibleChildren[] = [
                'id' => 0, 'name' => '导航配置', 'url' => 'navigation-manage.php',
                'new_tab' => 0, 'permission' => null,
            ];
        }
        $selected = nova_navigation_current_url_matches((string)$root['url']);
        foreach ($visibleChildren as $child) {
            $selected = $selected || nova_navigation_current_url_matches((string)$child['url']);
        }
        $class = $visibleChildren ? ' class="has-children' . ($selected ? ' active' : '') . '"' : '';
        $html .= '<li' . $class . '>' . nova_navigation_link($root, $selected);
        if ($visibleChildren) {
            $html .= '<ul class="sub-menu">';
            foreach ($visibleChildren as $child) {
                $html .= '<li>' . nova_navigation_link($child, nova_navigation_current_url_matches((string)$child['url'])) . '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</li>';
    }
    return $html . '</ul>';
}

function nova_navigation_audit(int $operatorId, string $action, ?int $targetId, mixed $before, mixed $after): void
{
    sql_query("INSERT INTO nova_navigation_audit_logs (operator_id, action, target_id, before_data, after_data, ip, created_at)
        VALUES ({$operatorId}, " . sqlesc($action) . ', ' . ($targetId ? intval($targetId) : 'NULL') . ', ' . sqlesc(json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . ', ' . sqlesc(json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . ', ' . sqlesc(getip()) . ', NOW())');
}

function nova_navigation_save(int $operatorId, array $input): int
{
    $id = max(0, (int)($input['id'] ?? 0));
    $name = trim((string)($input['name'] ?? ''));
    $url = trim((string)($input['url'] ?? ''));
    $parentId = max(0, (int)($input['parent_id'] ?? 0));
    $sortOrder = max(0, min(100000, (int)($input['sort_order'] ?? 0)));
    $newTab = ($input['new_tab'] ?? '0') === '1' ? 1 : 0;
    $isExternal = ($input['is_external'] ?? '0') === '1' ? 1 : 0;
    $permission = trim((string)($input['permission'] ?? ''));
    if ($name === '' || mb_strlen($name) > 255 || mb_strlen($url) > 255 || mb_strlen($permission) > 255) {
        throw new InvalidArgumentException('名称不能为空，名称、链接或权限内容过长。');
    }
    if ($parentId > 0) {
        $parentResult = sql_query("SELECT id, parent_id FROM navigations WHERE id = {$parentId} LIMIT 1");
        $parent = $parentResult ? mysql_fetch_assoc($parentResult) : null;
        if (!$parent || (int)$parent['parent_id'] !== 0 || $parentId === $id) {
            throw new InvalidArgumentException('父级菜单无效，只支持两级导航。');
        }
    }
    $before = null;
    if ($id > 0) {
        $beforeResult = sql_query("SELECT * FROM navigations WHERE id = {$id} LIMIT 1 FOR UPDATE");
        $before = $beforeResult ? mysql_fetch_assoc($beforeResult) : null;
        if (!$before) {
            throw new RuntimeException('导航项不存在。');
        }
        sql_query("UPDATE navigations SET name = " . sqlesc($name) . ', url = ' . sqlesc($url) . ", is_external = {$isExternal}, parent_id = {$parentId}, sort_order = {$sortOrder}, new_tab = {$newTab}, permission = " . ($permission === '' ? 'NULL' : sqlesc($permission)) . ", updated_at = NOW() WHERE id = {$id}") or throw new RuntimeException('更新导航失败。');
    } else {
        sql_query('INSERT INTO navigations (name, url, is_external, parent_id, sort_order, new_tab, permission, created_at, updated_at) VALUES (' . sqlesc($name) . ', ' . sqlesc($url) . ", {$isExternal}, {$parentId}, {$sortOrder}, {$newTab}, " . ($permission === '' ? 'NULL' : sqlesc($permission)) . ', NOW(), NOW())') or throw new RuntimeException('新增导航失败。');
        $id = mysql_insert_id();
    }
    $afterResult = sql_query("SELECT * FROM navigations WHERE id = {$id} LIMIT 1");
    $after = $afterResult ? mysql_fetch_assoc($afterResult) : null;
    nova_navigation_audit($operatorId, $before ? 'update' : 'create', $id, $before, $after);
    return $id;
}

function nova_navigation_delete(int $operatorId, int $id): void
{
    $childResult = sql_query("SELECT COUNT(*) AS total FROM navigations WHERE parent_id = {$id}");
    if ((int)mysql_fetch_assoc($childResult)['total'] > 0) {
        throw new RuntimeException('请先删除或移动该菜单下的二级导航。');
    }
    $beforeResult = sql_query("SELECT * FROM navigations WHERE id = {$id} LIMIT 1 FOR UPDATE");
    $before = $beforeResult ? mysql_fetch_assoc($beforeResult) : null;
    if (!$before) {
        throw new RuntimeException('导航项不存在。');
    }
    sql_query("DELETE FROM navigations WHERE id = {$id}") or throw new RuntimeException('删除导航失败。');
    nova_navigation_audit($operatorId, 'delete', $id, $before, null);
}

function nova_navigation_restore_snapshot(int $operatorId): void
{
    $snapshotResult = sql_query("SELECT payload FROM nova_navigation_snapshots WHERE snapshot_key = 'pre_lucky_draw_replacement' LIMIT 1");
    $snapshotRow = $snapshotResult ? mysql_fetch_assoc($snapshotResult) : null;
    $rows = $snapshotRow ? json_decode($snapshotRow['payload'], true) : null;
    if (!is_array($rows) || !$rows) {
        throw new RuntimeException('未找到更新转盘前的导航快照。');
    }
    $before = nova_navigation_rows();
    sql_query('DELETE FROM navigations') or throw new RuntimeException('无法清理当前导航。');
    foreach ($rows as $row) {
        sql_query('INSERT INTO navigations (id, name, url, is_external, parent_id, sort_order, new_tab, permission, created_at, updated_at) VALUES ('
            . intval($row['id']) . ', ' . sqlesc($row['name']) . ', ' . sqlesc($row['url']) . ', ' . intval($row['is_external']) . ', ' . intval($row['parent_id']) . ', ' . intval($row['sort_order']) . ', ' . intval($row['new_tab']) . ', ' . (empty($row['permission']) ? 'NULL' : sqlesc($row['permission'])) . ', ' . (empty($row['created_at']) ? 'NOW()' : sqlesc($row['created_at'])) . ', NOW())') or throw new RuntimeException('恢复导航快照失败。');
    }
    nova_navigation_audit($operatorId, 'restore_snapshot', null, $before, $rows);
}

add_filter('nexus_menu', 'nova_navigation_render');
