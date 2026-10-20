<?php
require_once("../include/bittorrent.php");
dbconn();
loggedinorreturn();

// 仅站长可访问
if (get_user_class() < UC_SYSOP) {
    stderr("权限不足", "只有运维以上等级才能访问此页面");
    stdfoot();
    exit;
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');
        // 防止SQL注入，使用sqlesc，且校验格式
        if ($name && $end_time) {
            // 检查时间格式
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $end_time);
            if ($dt !== false) {
                $end_time_sql = $dt->format('Y-m-d H:i:00');
                sql_query("INSERT INTO countdown_events (name, end_time, created_at, updated_at) VALUES (" . sqlesc($name) . ", " . sqlesc($end_time_sql) . ", NOW(), NOW())");
            }
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');
        if ($id && $name && $end_time) {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $end_time);
            if ($dt !== false) {
                $end_time_sql = $dt->format('Y-m-d H:i:00');
                sql_query("UPDATE countdown_events SET name=" . sqlesc($name) . ", end_time=" . sqlesc($end_time_sql) . ", updated_at=NOW() WHERE id=" . sqlesc($id));
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            sql_query("DELETE FROM countdown_events WHERE id=" . sqlesc($id));
        }
    }
    header("Location: countdown_manage.php");
    exit;
}

// 获取所有活动
$res = sql_query("SELECT * FROM countdown_events ORDER BY end_time ASC");
$events = [];
while ($row = mysql_fetch_assoc($res)) {
    $events[] = $row;
}

stdhead("活动倒计时管理");
echo '<h1 align="center">活动倒计时管理 (仅运维及以上可见）</h1>';

// 当前时间和时区
$now = new DateTime('now');
$tz = $now->getTimezone()->getName();
$nowStr = $now->format('Y-m-d H:i:s');
?>
<div style="max-width:600px;margin:20px auto;padding:16px;border:1px solid #ccc;border-radius:8px;">
    <h2>添加新活动</h2>
    <div style="margin-bottom:10px;color:#888;font-size:10px;text-align:center;">
        当前时间：<b><?php echo $nowStr; ?></b>　时区：<b><?php echo htmlspecialchars($tz); ?></b>
    </div>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <div style="margin-bottom:8px; display: flex; align-items: center;">
            <label for="name" style="min-width:80px;text-align:left;">活动名称：</label>
            <input type="text" id="name" name="name" required style="width:60%;text-align:left;">
        </div>
        <div style="margin-bottom:8px; display: flex; align-items: center;">
            <label for="end_time" style="min-width:80px;text-align:left;">截止时间：</label>
            <input type="datetime-local" id="end_time" name="end_time" required style="width:60%;text-align:left;">
            <span style="color:#888;font-size:12px;margin-left:8px;">格式：2025-07-21T00:00</span>
        </div>
        <div style="text-align:right;">
            <button type="submit">添加活动</button>
        </div>
    </form>
</div>

<?php if (!empty($events)): ?>
<div style="max-width:800px;margin:30px auto;">
    <h2>现有活动</h2>
    <table border="1" cellpadding="8" cellspacing="0" width="100%" style="background:#fff;">
        <tr style="background:#f5f5f5;font-weight:bold;">
            <td>ID</td>
            <td>活动名称</td>
            <td>截止时间</td>
            <td>操作</td>
        </tr>
        <?php foreach ($events as $event): ?>
        <tr>
            <form method="post" style="display:inline;">
                <input type="hidden" name="id" value="<?php echo (int)$event['id']; ?>">
                <td><?php echo (int)$event['id']; ?></td>
                <td style="text-align:left;">
                    <input type="text" name="name" value="<?php echo htmlspecialchars($event['name']); ?>" required style="width:90%;text-align:left;">
                </td>
                <td style="text-align:left;">
                    <input type="datetime-local" name="end_time" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($event['end_time']))); ?>" required style="text-align:left;">
                </td>
                <td>
                    <button type="submit" name="action" value="edit">保存</button>
                    <button type="submit" name="action" value="delete" onclick="return confirm('确定要删除该活动吗？');">删除</button>
                </td>
            </form>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php stdfoot(); ?> 