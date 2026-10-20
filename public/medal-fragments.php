<?php
require "../include/bittorrent.php";
dbconn(); loggedinorreturn();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../include/medal_fragments.php';
medal_fragment_ensure_tables();
$manager = medal_fragment_is_manager();
$uid = (int)$CURUSER['id'];
$drawResults = [];
$drawMessage = '';
$drawFlash = $_SESSION['nova_medal_draw_flash'] ?? null;
unset($_SESSION['nova_medal_draw_flash']);
if (is_array($drawFlash)) {
    $drawMessage = (string)($drawFlash['message'] ?? '');
    $drawResults = is_array($drawFlash['results'] ?? null) ? $drawFlash['results'] : [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'configure' && $manager) {
        $ids = array_unique(array_map('intval', (array)($_POST['medal_ids'] ?? [])));
        sql_query("UPDATE medal_fragment_config SET enabled=0");
        foreach ($ids as $id) if ($id > 0) {
            sql_query("INSERT INTO medal_fragment_config(medal_id,enabled) VALUES ({$id},1) ON DUPLICATE KEY UPDATE enabled=1");
        }
        header('Location: medal-fragments.php?ok=config'); exit;
    }
    if ($action === 'draw') {
        $drawCount = (int)($_POST['draw_count'] ?? 1);
        if (!in_array($drawCount, [1, 5, 10, 50], true)) $drawCount = 1;
        $cost = $drawCount * 2000;
        if (!medal_fragment_random_medal()) $drawMessage = '当前暂无开放合成的勋章。';
        elseif ((int)$CURUSER['seedbonus'] < $cost) $drawMessage = '魔力值不足，本轮需要 ' . number_format($cost) . ' 魔力。';
        else {
            sql_query("UPDATE users SET seedbonus=seedbonus-{$cost} WHERE id={$uid} AND seedbonus>={$cost}") or sqlerr(__FILE__,__LINE__);
            if (mysql_affected_rows() !== 1) $drawMessage = '魔力值扣除失败，请刷新后重试。';
            else {
                for ($drawIndex=1; $drawIndex<=$drawCount; $drawIndex++) {
                    $won = random_int(1,100) <= 8; $resultName = '';
                    $resultMedalId = $won ? medal_fragment_random_medal() : 0;
                    if ($won && $resultMedalId) {
                        $resultRow = mysql_fetch_assoc(sql_query("SELECT name FROM medals WHERE id=".(int)$resultMedalId." LIMIT 1"));
                        $resultName = (string)($resultRow['name'] ?? '');
                        $won = $resultName !== '' && medal_fragment_add($uid, $resultMedalId, 'draw', '魔力抽取');
                    }
                    $drawResults[]=['index'=>$drawIndex,'won'=>$won,'name'=>$resultName];
                }
                $drawMessage='本轮完成 '.$drawCount.' 次抽取，共消耗 '.number_format($cost).' 魔力。';
            }
        }
        $_SESSION['nova_medal_draw_flash'] = ['message' => $drawMessage, 'results' => $drawResults];
        header('Location: medal-fragments.php?draw=done');
        exit;
    }
    if ($action === 'merge') {
        $medalId = (int)($_POST['medal_id'] ?? 0);
        if (medal_fragment_user_owns($uid, $medalId)) stderr('勋章合成', '您已经拥有这枚勋章，不能重复佩戴，请前往交易所交换碎片。');
        if (!medal_fragment_consume_complete_set($uid, $medalId)) stderr('勋章合成', '必须集齐五个不同部位后才能合成。');
        $medal = mysql_fetch_assoc(sql_query("SELECT id,duration,priority FROM medals WHERE id={$medalId} LIMIT 1"));
        if (!$medal) stderr('勋章合成', '勋章不存在。');
        $expire = $medal['duration'] ? sqlesc(date('Y-m-d H:i:s', time() + (int)$medal['duration'] * 86400)) : 'NULL';
        sql_query("INSERT INTO user_medals(uid,medal_id,expire_at,status,priority,created_at,updated_at) VALUES({$uid},{$medalId},{$expire},1,".(int)$medal['priority'].',NOW(),NOW()) ON DUPLICATE KEY UPDATE status=1') or sqlerr(__FILE__,__LINE__);
        stderr('勋章合成', '合成成功，勋章已佩戴。');
    }
}

$owned = [];
$res = sql_query("SELECT m.id AS medal_id,m.name,m.image_large FROM medals m INNER JOIN medal_fragment_config c ON c.medal_id=m.id AND c.enabled=1 WHERE EXISTS(SELECT 1 FROM medal_fragments f WHERE f.uid={$uid} AND f.medal_id=m.id AND f.amount>0) OR EXISTS(SELECT 1 FROM medal_fragment_parts p WHERE p.uid={$uid} AND p.medal_id=m.id AND p.amount>0) ORDER BY m.priority DESC,m.id DESC");
while ($res && ($row=mysql_fetch_assoc($res))) $owned[]=$row;
$configured = [];
$res = sql_query("SELECT m.id,m.name,m.image_large,COALESCE(c.enabled,0) enabled FROM medals m LEFT JOIN medal_fragment_config c ON c.medal_id=m.id WHERE m.display_on_medal_page=1 ORDER BY m.priority DESC,m.id DESC");
while ($res && ($row=mysql_fetch_assoc($res))) $configured[]=$row;
$logRows=[];
if ($manager) { $res=sql_query("SELECT l.*,u.username,m.name FROM medal_fragment_logs l LEFT JOIN users u ON u.id=l.uid LEFT JOIN medals m ON m.id=l.medal_id ORDER BY l.id DESC LIMIT 100"); while($res&&($row=mysql_fetch_assoc($res)))$logRows[]=$row; }
stdhead('勋章碎片与合成'); begin_main_frame();
echo '<style>
.nova-fragment-center{max-width:1120px;margin:18px auto 34px;color:#24434a}
.nova-fragment-hero{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:28px 32px;border-radius:14px;background:linear-gradient(118deg,#103d49 0%,#096d70 54%,#0ca486 100%);box-shadow:0 16px 34px rgba(11,91,96,.2);color:#fff}
.nova-fragment-hero h1{margin:0 0 9px!important;color:#fff!important;font-size:28px!important;letter-spacing:.02em}.nova-fragment-hero h1:after{display:none!important}.nova-fragment-hero p{margin:0;color:rgba(255,255,255,.78);font-size:13px;line-height:1.8}
.nova-fragment-draw{display:inline-flex;align-items:center;justify-content:center;min-width:170px;padding:12px 18px;border-radius:8px;background:#f4d36e;color:#24484d!important;font-weight:800;text-decoration:none;box-shadow:0 7px 15px rgba(0,0,0,.13)}
.nova-fragment-section{margin-top:18px;padding:20px 22px;border:1px solid #d8e8e8;border-radius:10px;background:rgba(255,255,255,.84);box-shadow:0 7px 20px rgba(35,76,82,.06)}.nova-fragment-section h2{margin:0 0 14px!important;color:#173f49!important;font-size:18px!important}.nova-fragment-section h2:after{display:none!important}
.nova-fragment-section table{border-collapse:separate;border-spacing:0;overflow:hidden;border:1px solid #dce9e9;border-radius:8px}.nova-fragment-section th{padding:11px 13px;background:#eaf5f4;color:#376269;font-weight:800;text-align:left}.nova-fragment-section td{padding:11px 13px;border-top:1px solid #e6eeee;background:#fff}.nova-fragment-section tr:hover td{background:#f7fbfa}
.nova-fragment-rule-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px}.nova-fragment-rule{min-height:96px;padding:14px;border:1px solid #dcebea;border-radius:8px;background:linear-gradient(145deg,#fff,#f2f9f8)}.nova-fragment-rule strong{display:block;margin-bottom:7px;color:#0b8b7c;font-size:13px}.nova-fragment-rule span{color:#647d82;font-size:12px;line-height:1.7}
.nova-fragment-section button{min-height:32px;padding:0 13px;border:0;border-radius:6px;background:#0d9b89;color:#fff;font-weight:700;cursor:pointer}.nova-fragment-section button:hover{background:#087e73}
.nova-draw-panel{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-top:18px;padding:17px 20px;border:1px solid #d8e8e8;border-radius:10px;background:#fff;box-shadow:0 7px 20px rgba(35,76,82,.06)}.nova-draw-panel h2{margin:0 0 5px!important;color:#173f49!important;font-size:17px!important}.nova-draw-panel p{margin:0;color:#71868a;font-size:12px}.nova-draw-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.nova-draw-actions button{min-width:74px;background:#f1b739;color:#4f3a08}.nova-draw-actions button:hover{background:#dc9b1e}.nova-draw-result{margin-top:14px;padding:12px 15px;border:1px solid #d8e8e8;border-radius:8px;background:#f8fcfb}.nova-draw-result table{margin-top:8px}.nova-draw-result .win{color:#087f70;font-weight:800}.nova-draw-result .lose{color:#88999b}
.nova-draw-result table{display:none}.nova-draw-grid{display:grid;grid-template-columns:repeat(5,minmax(150px,1fr));gap:8px;margin-top:10px}.nova-draw-item{display:grid;grid-template-columns:30px minmax(0,1fr);align-items:center;min-height:34px;padding:0 9px;border:1px solid #e0eceb;border-radius:6px;background:#fff;font-size:12px}.nova-draw-item .draw-number{color:#5d777c;font-weight:800}.nova-draw-item .win{color:#087f70;font-weight:800}.nova-draw-item .lose{color:#88999b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.nova-fragment-formula{display:flex;align-items:center;justify-content:center;gap:9px;flex-wrap:wrap;margin:0 0 15px;padding:13px 15px;border:1px solid #bcded9;border-radius:8px;background:linear-gradient(135deg,#eaf8f5,#f7fbfc);color:#27565d}.nova-fragment-formula span{padding:7px 11px;border-radius:6px;background:#fff;color:#176d68;font-size:12px;font-weight:800;box-shadow:0 2px 7px rgba(28,78,83,.07)}.nova-fragment-formula b{color:#0b9686;font-size:16px}.nova-fragment-formula-note{margin:-6px 0 14px;color:#71868a;font-size:12px;text-align:center;line-height:1.7}
@media(max-width:1100px){.nova-draw-grid{grid-template-columns:repeat(4,minmax(145px,1fr))}}@media(max-width:850px){.nova-draw-grid{grid-template-columns:repeat(3,minmax(140px,1fr))}}@media(max-width:760px){.nova-fragment-hero{align-items:flex-start;flex-direction:column;padding:22px}.nova-fragment-hero h1{font-size:23px!important}.nova-fragment-draw{width:100%}.nova-fragment-rule-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.nova-fragment-section{padding:16px 12px;overflow-x:auto}.nova-draw-panel{align-items:flex-start;flex-direction:column}.nova-draw-actions{width:100%}.nova-draw-actions button{flex:1}.nova-draw-grid{grid-template-columns:repeat(2,minmax(125px,1fr));gap:6px}}
@media(max-width:430px){.nova-fragment-rule-grid{grid-template-columns:1fr}}
</style><div class="nova-fragment-center"><div class="nova-fragment-hero"><div><h1>勋章碎片中心</h1><p>碎片收集与勋章墙独立计算；已有勋章仍可继续获得碎片，完整套装仅可进入交易所交换。</p></div></div>';
echo '<section class="nova-fragment-section"><h2>碎片获取规则</h2><div class="nova-fragment-formula"><span>有效参与资格</span><b>×</b><span>当前活动状态</span><b>×</b><span>持续行为记录</span><b>×</b><span>系统随机判定</span><b>=</b><span>碎片获取结果</span></div><p class="nova-fragment-formula-note">系统仅计算符合条件的有效行为；资格满足后进入随机判定，碎片类型从当前开放合成的勋章中随机产生。</p><div class="nova-fragment-rule-grid"><div class="nova-fragment-rule"><strong>官种保种</strong><span>系统会根据用户的有效保种状态进行判断。保官种时间越长，获取碎片的机会越大；持续保种超过 48 小时后，才进入碎片获取范围。</span></div><div class="nova-fragment-rule"><strong>活动官种</strong><span>参与保种区及其他官种活动的资源时，系统会结合资源当前活动状态、做种状态和持续时间进行判断。资源离开活动后，不再按活动规则计算。</span></div><div class="nova-fragment-rule"><strong>连续签到</strong><span>连续签到达到系统要求后，签到用户会进入随机抽取范围。中断连续签到后，需要按照站点签到规则重新累计。</span></div><div class="nova-fragment-rule"><strong>整点在线</strong><span>系统会在整点从符合在线条件的用户中动态抽取奖励对象。在线并不代表必定获得，最终结果由系统随机决定。</span></div><div class="nova-fragment-rule"><strong>随机归属</strong><span>获得碎片时，系统会从当前开放合成的勋章中随机选择碎片类型，不保证每次都是同一枚勋章。</span></div><div class="nova-fragment-rule"><strong>合成与交易</strong><span>必须集齐五个不同部位才能组成完整勋章。已经拥有该勋章的用户仍可继续收集，但不能重复佩戴，相关碎片只能进入交易所交换。</span></div></div></section>';
echo '<section class="nova-draw-panel"><div><h2>随机碎片抽取</h2><p>每次抽取消耗 2000 魔力，结果将在当前页面显示。</p></div><form class="nova-draw-actions" method="post"><input type="hidden" name="action" value="draw"><button name="draw_count" value="1">抽奖 1 次</button><button name="draw_count" value="5">抽奖 5 次</button><button name="draw_count" value="10">抽奖 10 次</button><button name="draw_count" value="50">抽奖 50 次</button></form></section>';
if ($drawMessage !== '') { echo '<div class="nova-draw-result"><strong>'.htmlspecialchars($drawMessage).'</strong>'; if ($drawResults) { echo '<div class="nova-draw-grid">'; foreach($drawResults as $result) echo '<div class="nova-draw-item"><span class="draw-number">'.(int)$result['index'].'</span><span class="'.($result['won']?'win':'lose').'">'.($result['won']?'获得「'.htmlspecialchars($result['name']).'」碎片':'未获得碎片').'</span></div>'; echo '</div>'; } echo '</div>'; }
echo '<section class="nova-fragment-section"><h2>我的碎片</h2><table width="100%"><tr><th>勋章</th><th>碎片进度</th><th>操作</th></tr>';
foreach($owned as $row){$medalId=(int)$row['medal_id'];$partCount=medal_fragment_complete_part_count($uid,$medalId);$total=medal_fragment_total($uid,$medalId);$alreadyOwned=medal_fragment_user_owns($uid,$medalId);$action=$partCount<5?'等待收集':($alreadyOwned?'<a href="medal-exchange.php">仅可前往交易</a>':'<form method="post"><input type="hidden" name="action" value="merge"><input type="hidden" name="medal_id" value="'.$medalId.'"><button>合成并佩戴</button></form>');echo '<tr><td>'.htmlspecialchars($row['name']).($alreadyOwned?' <small>（勋章墙已拥有）</small>':'').'</td><td>'.$partCount.' / 5（共 '.$total.' 个）</td><td>'.$action.'</td></tr>';}
echo '</table></section>';
if($manager){echo '<section class="nova-fragment-section"><h2>管理配置与审计</h2><form method="post"><input type="hidden" name="action" value="configure"><div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px">'; foreach($configured as $row) echo '<label><input type="checkbox" name="medal_ids[]" value="'.(int)$row['id'].'" '.((int)$row['enabled']?'checked':'').'> '.htmlspecialchars($row['name']).'</label>'; echo '</div><p><button type="submit">保存可合成勋章</button></p></form><table width="100%"><tr><th>用户</th><th>勋章</th><th>来源</th><th>时间</th></tr>'; foreach($logRows as $row) echo '<tr><td>'.htmlspecialchars($row['username']??'').'</td><td>'.htmlspecialchars($row['name']??'').'</td><td>'.htmlspecialchars($row['source']).'</td><td>'.htmlspecialchars($row['created_at']).'</td></tr>'; echo '</table></section>';}
echo '</div>'; end_main_frame(); stdfoot();
