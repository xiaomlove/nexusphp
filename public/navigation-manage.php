<?php
require_once __DIR__ . '/../include/bittorrent.php';
dbconn();
require_once __DIR__ . '/../include/nova_navigation.php';

if (!$CURUSER || !nova_navigation_is_admin()) {
    stdhead('导航配置');
    stdmsg('权限不足', '只有管理员及以上账号可以配置导航。');
    stdfoot();
    exit;
}

$userId = (int)$CURUSER['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!nova_navigation_verify_token((string)($_POST['token'] ?? ''), $userId)) {
        stderr('请求失效', '请刷新页面后重试。');
    }
    $action = (string)($_POST['action'] ?? '');
    sql_query('START TRANSACTION');
    try {
        if ($action === 'save') {
            nova_navigation_save($userId, $_POST);
            $message = '导航项已保存';
        } elseif ($action === 'delete') {
            nova_navigation_delete($userId, (int)($_POST['id'] ?? 0));
            $message = '导航项已删除';
        } elseif ($action === 'restore') {
            nova_navigation_restore_snapshot($userId);
            $message = '已恢复更新转盘前的导航配置';
        } else {
            throw new InvalidArgumentException('未知操作。');
        }
        sql_query('COMMIT');
        header('Location: navigation-manage.php?message=' . rawurlencode($message));
        exit;
    } catch (Throwable $e) {
        sql_query('ROLLBACK');
        $error = $e->getMessage();
    }
}

$rows = nova_navigation_rows();
$roots = array_values(array_filter($rows, static fn (array $row) => (int)$row['parent_id'] === 0));
$children = [];
foreach ($rows as $row) {
    if ((int)$row['parent_id'] > 0) {
        $children[(int)$row['parent_id']][] = $row;
    }
}
$token = nova_navigation_token($userId);
$parentOptions = static function (int $selected = 0) use ($roots): string {
    $html = '<option value="0"' . ($selected === 0 ? ' selected' : '') . '>一级导航</option>';
    foreach ($roots as $root) {
        $html .= '<option value="' . (int)$root['id'] . '"' . ($selected === (int)$root['id'] ? ' selected' : '') . '>' . htmlspecialchars($root['name']) . '</option>';
    }
    return $html;
};
$renderRow = static function (array $row, bool $child = false) use ($token, $parentOptions): void {
    ?>
    <form method="post" class="nova-nav-editor-row <?php echo $child ? 'is-child' : 'is-root'; ?>">
      <input type="hidden" name="token" value="<?php echo $token; ?>"><input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
      <div class="nova-nav-level"><?php echo $child ? '<span>↳</span> 二级' : '<span>◆</span> 一级'; ?></div>
      <label><span>名称</span><input name="name" maxlength="255" required value="<?php echo htmlspecialchars($row['name']); ?>"></label>
      <label class="url"><span>链接</span><input name="url" maxlength="255" value="<?php echo htmlspecialchars($row['url']); ?>"></label>
      <label><span>父级</span><select name="parent_id"><?php echo $parentOptions((int)$row['parent_id']); ?></select></label>
      <label class="small"><span>排序</span><input type="number" name="sort_order" min="0" value="<?php echo (int)$row['sort_order']; ?>"></label>
      <label><span>权限</span><input name="permission" maxlength="255" placeholder="留空公开" value="<?php echo htmlspecialchars((string)$row['permission']); ?>"></label>
      <label class="toggle"><input type="checkbox" name="new_tab" value="1" <?php echo (int)$row['new_tab'] === 1 ? 'checked' : ''; ?>><span>新窗口</span></label>
      <input type="hidden" name="is_external" value="<?php echo (int)$row['is_external']; ?>">
      <div class="nova-nav-row-actions"><button type="submit" name="action" value="save">保存</button><button type="submit" name="action" value="delete" class="delete" onclick="return confirm('确定删除这个导航项吗？')">删除</button></div>
    </form>
    <?php
};

stdhead('导航配置');
?>
<style>
.nova-nav-manager{box-sizing:border-box;width:65%;max-width:calc(100vw - 18px);margin:18px auto 34px;color:#294f55;letter-spacing:0}
.nova-nav-manager-head{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:22px 24px;background:#eaf9f7;border:1px solid #c3e4df;border-radius:8px 8px 0 0}.nova-nav-manager-head small{display:block;color:#118b82;font-weight:900;letter-spacing:1.3px}.nova-nav-manager-head h1{margin:4px 0 5px;font-size:25px;color:#224f55}.nova-nav-manager-head p{margin:0;color:#668187}.nova-nav-manager-head form{flex:0 0 auto}.nova-nav-restore{height:40px;padding:0 14px;border:1px solid #e4b98e;border-radius:6px;background:#fff8ef;color:#b5662b;font-weight:850;cursor:pointer}
.nova-nav-notice{padding:11px 18px;border:1px solid #c8e8d8;border-top:0;background:#f1fbf6;color:#24745d;font-weight:750}.nova-nav-error{border-color:#edc7c9;background:#fff2f2;color:#a74a53}
.nova-nav-toolbar{display:flex;align-items:center;justify-content:space-between;padding:13px 18px;border:1px solid #d5e7e4;border-top:0;background:#fff}.nova-nav-toolbar b{color:#147f76}.nova-nav-toolbar span{color:#748d91;font-size:12px}
.nova-nav-editor{border:1px solid #d5e7e4;border-top:0;background:#fff}.nova-nav-group+.nova-nav-group{border-top:5px solid #edf5f4}
.nova-nav-editor-row{display:grid;grid-template-columns:74px minmax(120px,.8fr) minmax(230px,2fr) minmax(130px,.9fr) 76px minmax(110px,.75fr) 72px 126px;gap:9px;align-items:end;box-sizing:border-box;padding:11px 14px;margin:0;border-bottom:1px solid #e8f0ef;background:#fff}.nova-nav-editor-row.is-root{background:#f8fcfb}.nova-nav-editor-row.is-child{padding-left:28px}.nova-nav-editor-row:last-child{border-bottom:0}
.nova-nav-level{align-self:center;color:#607c81;font-weight:850;white-space:nowrap}.nova-nav-level span{color:#12a28d}.nova-nav-editor-row label{display:flex;flex-direction:column;gap:5px;min-width:0;color:#70888d;font-size:11px;font-weight:750}.nova-nav-editor-row input,.nova-nav-editor-row select{box-sizing:border-box;width:100%;height:36px;min-width:0;padding:0 9px;border:1px solid #c9ddda;border-radius:5px;background:#fff;color:#31575c;font-size:13px}.nova-nav-editor-row input:focus,.nova-nav-editor-row select:focus{outline:2px solid rgba(19,158,139,.18);border-color:#18a18d}.nova-nav-editor-row .toggle{align-self:center;flex-direction:row;align-items:center;gap:6px;font-size:12px;white-space:nowrap}.nova-nav-editor-row .toggle input{width:16px;height:16px}.nova-nav-row-actions{display:flex;gap:6px}.nova-nav-row-actions button,.nova-nav-add button{height:36px;padding:0 12px;border:0;border-radius:5px;background:#138f82;color:#fff;font-weight:850;cursor:pointer}.nova-nav-row-actions .delete{background:#bf5b64}
.nova-nav-add{margin-top:18px;border:1px solid #d4e6e3;border-radius:8px;background:#fff;overflow:hidden}.nova-nav-add h2{margin:0!important;padding:14px 18px;background:#eff9f7;color:#28565c;font-size:17px!important}.nova-nav-add form{display:grid;grid-template-columns:minmax(130px,.8fr) minmax(240px,2fr) minmax(130px,.9fr) 80px minmax(120px,.8fr) 80px;gap:10px;align-items:end;padding:16px}.nova-nav-add label{display:flex;flex-direction:column;gap:5px;color:#70888d;font-size:11px;font-weight:750}.nova-nav-add input,.nova-nav-add select{box-sizing:border-box;width:100%;height:37px;padding:0 9px;border:1px solid #c9ddda;border-radius:5px;background:#fff;color:#31575c}.nova-nav-add .toggle{flex-direction:row;align-items:center;height:37px}.nova-nav-add .toggle input{width:16px;height:16px}.nova-nav-guide{margin-top:14px;padding:14px 17px;border-left:4px solid #19a18e;background:#f2faf8;color:#607a7f;line-height:1.7}.nova-nav-guide code{color:#167c73}
@media(max-width:1450px){.nova-nav-manager{width:92%}.nova-nav-editor-row{grid-template-columns:65px minmax(110px,.8fr) minmax(190px,1.8fr) minmax(120px,.8fr) 70px minmax(100px,.7fr) 68px 118px}}
@media(max-width:900px){.nova-nav-manager{width:calc(100vw - 18px);max-width:calc(100vw - 18px)}.nova-nav-manager-head{align-items:flex-start;flex-direction:column}.nova-nav-editor-row,.nova-nav-add form{grid-template-columns:1fr 1fr}.nova-nav-editor-row .url{grid-column:1/-1}.nova-nav-level{grid-column:1/-1}.nova-nav-row-actions{align-self:end}.nova-nav-add button{width:100%}}
</style>
<main class="nova-nav-manager">
  <header class="nova-nav-manager-head"><div><small>NOVAHD NAVIGATION</small><h1>导航配置</h1><p>导航已从抽奖扩展中独立，修改后全站立即生效。</p></div><form method="post"><input type="hidden" name="token" value="<?php echo $token; ?>"><button class="nova-nav-restore" name="action" value="restore" onclick="return confirm('确定恢复为更新转盘前的导航配置吗？当前修改会被覆盖。')">恢复更新前配置</button></form></header>
  <?php if (!empty($_GET['message'])): ?><div class="nova-nav-notice"><?php echo htmlspecialchars((string)$_GET['message']); ?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="nova-nav-notice nova-nav-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <div class="nova-nav-toolbar"><b><?php echo count($roots); ?> 个一级导航 · <?php echo count($rows) - count($roots); ?> 个二级导航</b><span>排序值越小越靠前；权限可填写用户等级数字或权限名称</span></div>
  <section class="nova-nav-editor">
    <?php foreach ($roots as $root): ?><div class="nova-nav-group"><?php $renderRow($root); foreach ($children[(int)$root['id']] ?? [] as $child) { $renderRow($child, true); } ?></div><?php endforeach; ?>
  </section>
  <section class="nova-nav-add"><h2>新增导航</h2><form method="post"><input type="hidden" name="token" value="<?php echo $token; ?>"><label>名称<input name="name" maxlength="255" required></label><label>链接<input name="url" maxlength="255"></label><label>父级<select name="parent_id"><?php echo $parentOptions(0); ?></select></label><label>排序<input type="number" name="sort_order" min="0" value="200"></label><label>权限<input name="permission" placeholder="留空公开"></label><label class="toggle"><input type="checkbox" name="new_tab" value="1"> 新窗口</label><input type="hidden" name="is_external" value="0"><button type="submit" name="action" value="save">新增</button></form></section>
  <div class="nova-nav-guide">权限填写示例：<code>12</code> 表示发布员及以上，<code>staffmem</code> 表示管理组权限；留空表示所有已登录用户可见。一级导航留空链接时会作为纯下拉菜单使用。</div>
</main>
<?php stdfoot(); ?>
