<?php
/**
 * NexusPHP 发种大赛活动页面
 * 特性: 现代化 UI, 固定起始日期, 限制显示前N名, 前端排序, AJAX 详情弹窗
 */

require "../include/bittorrent.php";
dbconn();
loggedinorreturn(); // 确保用户已登录

// === 核心配置项（修改这里即可调整活动规则）===
define('CONTEST_START_DATE', '2025-11-21'); // 统计起始日期（格式：YYYY-MM-DD）
define('MAX_DISPLAY_RANK', 20); // 最多显示前N名（默认20）

// 处理AJAX请求，用于获取用户发种详情
if (isset($_GET['action']) && $_GET['action'] == 'get_details') {
    $userid = isset($_GET['userid']) ? intval($_GET['userid']) : 0;
    $start_date = CONTEST_START_DATE; // 使用配置的固定日期

    if ($userid > 0) {
        $sql = "SELECT name, CONVERT_TZ(added, '+00:00', '+08:00') as added_utc8 
                FROM torrents 
                WHERE owner = " . sqlesc($userid) . " AND added >= " . sqlesc($start_date . " 00:00:00") . "
                ORDER BY added DESC";
        $res = sql_query($sql);
        $torrents = [];
        while ($row = mysql_fetch_assoc($res)) {
            $torrents[] = [
                'name' => htmlspecialchars($row['name']),
                'added' => $row['added_utc8']
            ];
        }
        header('Content-Type: application/json');
        echo json_encode($torrents);
    }
    exit(); // 结束脚本，不渲染下面的HTML
}

stdhead("发种大赛排名");

// === CSS 样式注入 (复用 vanity_transfer.php 样式并增加弹窗样式) ===
?>
<style>
    /* --- 核心样式 (来自 vanity_transfer.php) --- */
    .v-container { max-width: 1000px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; color: #333; }
    .v-card { background: #fff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); padding: 30px; margin-bottom: 25px; border: 1px solid #eaeaea; }
    .v-title { margin-top: 0; font-size: 24px; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px; }
    .v-form-group { margin-bottom: 20px; }
    .v-label { display: block; margin-bottom: 8px; font-weight: 600; color: #444; }
    .v-input { width: 250px; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; transition: border 0.2s; box-sizing: border-box; }
    .v-input:focus { border-color: #007bff; outline: none; box-shadow: 0 0 0 3px rgba(0,123,255,0.1); }
    .v-btn { display: inline-block; padding: 12px 25px; border: none; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.2s; text-decoration: none; }
    .v-btn-primary { background: #007bff; color: #fff; }
    .v-btn-primary:hover { background: #0056b3; }
    .v-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    .v-table th, .v-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
    .v-table th { background-color: #f8f9fa; color: #555; font-weight: 600; cursor: pointer; user-select: none; }
    .v-table th:hover { background-color: #f1f3f5; }
    .v-table th .sort-icon { margin-left: 5px; color: #aaa; font-weight: normal; }
    .v-table tr:last-child td { border-bottom: none; }
    .v-alert-info { background: #e3f2fd; border-left: 5px solid #2196f3; color: #0d47a1; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
    .v-config-note { background: #f5fafe; border-radius: 6px; padding: 12px 15px; margin-bottom: 20px; font-size: 14px; color: #4a5568; border: 1px solid #dee2e6; }
    .v-config-note strong { color: #2d3748; }
    
    /* --- 新增：活动规则和奖励样式 --- */
    .v-rule-section { background: #fafafa; border-radius: 8px; padding: 20px; margin-bottom: 25px; border: 1px solid #f0f0f0; }
    .v-rule-title { margin-top: 0; font-size: 18px; font-weight: 600; color: #2c3e50; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
    .v-rule-list { margin: 0; padding-left: 20px; line-height: 1.8; color: #444; }
    .v-rule-list li { margin-bottom: 8px; }
    .v-rule-list li:last-child { margin-bottom: 0; }
    .v-reward-section { background: #fef7fb; border-radius: 8px; padding: 20px; margin-bottom: 25px; border: 1px solid #fce4ec; }
    .v-reward-list { margin: 0; padding-left: 20px; line-height: 1.8; color: #444; }
    .v-reward-list li { margin-bottom: 8px; }
    .v-reward-list li:last-child { margin-bottom: 0; }
    .v-highlight { color: #d32f2f; font-weight: 600; }
    .v-tag-example { background: #f0f0f0; padding: 2px 8px; border-radius: 4px; font-family: monospace; font-size: 13px; }
    
    /* --- 新增：弹窗 (Modal) 样式 --- */
    .v-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; display: none; align-items: center; justify-content: center; }
    .v-modal-content { background: #fff; border-radius: 8px; padding: 30px; width: 90%; max-width: 800px; max-height: 80vh; overflow-y: auto; box-shadow: 0 5px 20px rgba(0,0,0,0.2); position: relative; }
    .v-modal-close { position: absolute; top: 15px; right: 20px; font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; border: none; background: none; }
    .v-modal-close:hover { color: #333; }
    .v-modal-title { margin-top: 0; font-size: 20px; font-weight: 600; color: #2c3e50; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px; }
</style>

<?php
// === 逻辑处理 ===

$start_date = CONTEST_START_DATE; // 固定使用配置的起始日期
$max_rank = MAX_DISPLAY_RANK; // 最多显示的排名数量

// 主查询：联结torrents和users表，按用户分组统计发种数量，限制显示前N名
// 注意: t.added 存储的是 UTC 时间, 我们直接用 UTC 日期进行比较
$sql = "SELECT t.owner, u.username, COUNT(t.id) as torrent_count
        FROM torrents AS t
        JOIN users AS u ON t.owner = u.id
        WHERE t.added >= " . sqlesc($start_date . " 00:00:00") . "
        GROUP BY t.owner
        ORDER BY torrent_count DESC
        LIMIT " . intval($max_rank); // 限制显示数量

$res = sql_query($sql);

$rankings = [];
while ($row = mysql_fetch_assoc($res)) {
    $rankings[] = $row;
}

?>

<div class="v-container">
    <div class="v-card">
        <h2 class="v-title">🚀 发种大赛排名</h2>

        <div class="v-alert v-alert-info">
            <strong>页面说明：</strong> 本页面统计自 <?php echo $start_date; ?>起，所有用户的发种数量，仅显示前 <?php echo $max_rank; ?> 名。点击表头可进行排序。
        </div>

        <!-- 活动规则区域 -->
        <div class="v-rule-section">
            <h3 class="v-rule-title">📋 发种大赛规则</h3>
            <ul class="v-rule-list">
                <li>1、发种大赛活动日期：<span class="v-highlight">11月21日至12月21日</span></li>
                <li>2、发种格式：仅支持转载类型资源（游戏、音乐、电影、电视剧、动漫）</li>
                <li>3、发种标签格式：转载 + 类型（例：<span class="v-tag-example">[转载] [电影]</span>、<span class="v-tag-example">[转载] [游戏]</span>）</li>
                <li>4、发种权限：<span class="v-highlight">User</span> 及以上等级用户均可参与</li>
                <li>5、电视剧分集不纳入活动（不要发布分集内容，完整剧集可正常参与）</li>
                <li>6、请认真阅读发种规则后再发布种子，违规种子将被删除，不计入统计</li>
            </ul>
        </div>

        <!-- 活动奖励区域 -->
        <div class="v-reward-section">
            <h3 class="v-rule-title">🏆 发种大赛奖励</h3>
            <ul class="v-reward-list">
                <li>1、第1名：<span class="v-highlight">500000魔力</span> + 永久VIP + 永久彩色用户名</li>
                <li>2、第2-10名：<span class="v-highlight">300000魔力</span> + 1个月VIP + 永久彩色用户名</li>
                <li>3、第11-20名：<span class="v-highlight">100000魔力</span> + 1个月VIP</li>
            </ul>
        </div>

        <!-- 排名表格 -->
        <table class="v-table" id="rank-table">
            <thead>
                <tr>
                    <th width="10%">名次</th>
                    <th width="15%" data-sort="number">用户ID</th>
                    <th width="35%" data-sort="string">用户名</th>
                    <th width="20%" data-sort="number">发种数量</th>
                    <th width="20%">种子列表</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($rankings) > 0): ?>
                    <?php foreach ($rankings as $index => $row): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo $row['owner']; ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo $row['torrent_count']; ?></td>
                            <td>
                                <button class="v-btn v-btn-primary details-btn" 
                                        data-userid="<?php echo $row['owner']; ?>" 
                                        data-username="<?php echo htmlspecialchars($row['username']); ?>"
                                        style="padding: 8px 15px; font-size: 13px;">
                                    详情
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 30px; color: #888;">
                            自 <?php echo $start_date; ?> 起，暂无用户发布种子。
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 详情弹窗 (Modal) HTML结构 -->
<div class="v-modal-overlay" id="details-modal-overlay">
    <div class="v-modal-content">
        <button class="v-modal-close" id="modal-close-btn">&times;</button>
        <h3 class="v-modal-title" id="modal-title">用户详情</h3>
        <div id="modal-body">
            <!-- AJAX内容将加载于此 -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- 功能1: 表格前端排序 ---
    const table = document.getElementById('rank-table');
    const headers = table.querySelectorAll('th[data-sort]');
    const maxRank = <?php echo $max_rank; ?>; // 传递配置的最大显示数量
    
    headers.forEach(header => {
        header.addEventListener('click', () => {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const colIndex = Array.from(header.parentNode.children).indexOf(header);
            const sortType = header.dataset.sort;
            const isAsc = header.dataset.order === 'asc';

            // 更新排序方向
            headers.forEach(h => h.querySelector('.sort-icon') ? h.querySelector('.sort-icon').remove() : null);
            header.dataset.order = isAsc ? 'desc' : 'asc';

            const icon = document.createElement('span');
            icon.className = 'sort-icon';
            icon.textContent = isAsc ? ' ↑' : ' ↓';
            header.appendChild(icon);

            // 排序逻辑
            rows.sort((a, b) => {
                const valA = a.children[colIndex].textContent.trim();
                const valB = b.children[colIndex].textContent.trim();
                
                let comparison = 0;
                if (sortType === 'number') {
                    comparison = parseFloat(valA) - parseFloat(valB);
                } else {
                    comparison = valA.localeCompare(valB, 'zh-Hans-CN');
                }
                
                return isAsc ? -comparison : comparison;
            });

            // 重新渲染表格（排序后更新名次）
            rows.forEach((row, index) => {
                row.children[0].textContent = index + 1; // 更新名次列
                tbody.appendChild(row);
            });
        });
    });

    // --- 功能2: AJAX详情弹窗 ---
    const modalOverlay = document.getElementById('details-modal-overlay');
    const modalTitle = document.getElementById('modal-title');
    const modalBody = document.getElementById('modal-body');
    const startDate = '<?php echo CONTEST_START_DATE; ?>'; // 固定传递配置日期

    table.addEventListener('click', function(e) {
        if (e.target.classList.contains('details-btn')) {
            const button = e.target;
            const userId = button.dataset.userid;
            const username = button.dataset.username;

            modalTitle.textContent = `用户 "${username}" 的发种详情（自 ${startDate} 起）`;
            modalBody.innerHTML = '<p style="text-align:center; color:#888;">正在加载数据...</p>';
            modalOverlay.style.display = 'flex';

            // 发起AJAX请求
            fetch(`?action=get_details&userid=${userId}&start_date=${startDate}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalBody.innerHTML = `<p style="color:red;">加载失败: ${data.error}</p>`;
                        return;
                    }

                    if (data.length === 0) {
                        modalBody.innerHTML = `<p>该用户自 ${startDate} 起没有发布种子。</p>`;
                        return;
                    }

                    let tableHtml = '<table class="v-table"><thead><tr><th width="5%">序号</th><th>种子名称</th><th width="25%">发布时间 (UTC+8)</th></tr></thead><tbody>';
                    data.forEach((torrent, index) => {
                        tableHtml += `<tr><td>${index + 1}</td><td>${torrent.name}</td><td>${torrent.added}</td></tr>`;
                    });
                    tableHtml += '</tbody></table>';
                    
                    modalBody.innerHTML = tableHtml;
                })
                .catch(error => {
                    modalBody.innerHTML = `<p style="color:red;">发生网络错误，无法加载详情。 ${error}</p>`;
                });
        }
    });

    // 关闭弹窗
    function closeModal() {
        modalOverlay.style.display = 'none';
    }

    document.getElementById('modal-close-btn').addEventListener('click', closeModal);
    modalOverlay.addEventListener('click', function(e) {
        if (e.target === modalOverlay) {
            closeModal();
        }
    });
});
</script>

<?php
stdfoot();
?>