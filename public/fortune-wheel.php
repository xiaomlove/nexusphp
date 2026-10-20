<?php
require_once __DIR__ . '/../include/bittorrent.php';
dbconn();
require_once __DIR__ . '/../include/nova_lucky_draw.php';

if (!$CURUSER) {
    stdhead('幸运抽奖');
    stdmsg('提示', '请先登录后访问。');
    stdfoot();
    exit;
}

$isAdmin = nova_draw_is_admin();
$settings = nova_draw_settings();
if (($settings['enabled'] ?? 'yes') !== 'yes' && !$isAdmin) {
    stdhead('幸运抽奖');
    stdmsg('提示', '幸运抽奖活动暂未开放。');
    stdfoot();
    exit;
}
$prizes = nova_draw_prizes(false);
$activePrizes = array_values(array_filter($prizes, static fn (array $prize) => (int)$prize['enabled'] === 1 && (float)$prize['weight'] > 0 && ((int)$prize['stock'] === -1 || (int)$prize['stock'] > 0)));
$history = nova_draw_user_history((int)$CURUSER['id'], 30);
$winners = nova_draw_recent_winners(12);
$todayCount = nova_draw_today_count((int)$CURUSER['id']);
$freeLeft = max(0, (int)$settings['daily_free_draws'] - $todayCount);
$medals = [];
if ($isAdmin) {
    $medalResult = sql_query('SELECT id, name FROM medals ORDER BY priority DESC, id ASC');
    while ($medalResult && ($medal = mysql_fetch_assoc($medalResult))) {
        $medals[] = $medal;
    }
}
$typeLabels = [
    'bonus' => '魔力值', 'upload' => '上传量', 'vip_days' => 'VIP 天数',
    'rainbow_id_days' => '彩虹 ID 天数', 'invite_temp' => '临时邀请',
    'invite_perm' => '永久邀请', 'attendance_card' => '补签卡',
    'rename_card' => '改名卡', 'medal' => '勋章', 'nothing' => '谢谢参与',
];
$publicPrizes = array_map(static fn (array $prize) => [
    'id' => (int)$prize['id'], 'name' => $prize['name'], 'color' => $prize['color'],
], $activePrizes);

stdhead('幸运抽奖');
?>
<style>
.nova-draw-page{box-sizing:border-box;width:65%;max-width:calc(100vw - 18px);margin:18px auto 32px;color:#294f55;letter-spacing:0}
.nova-draw-hero{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:22px 26px;background:#eaf9f7;border:1px solid #bfe4df;border-radius:8px 8px 0 0}
.nova-draw-kicker{display:block;color:#108d83;font-size:12px;font-weight:900;letter-spacing:1.6px}.nova-draw-hero h1{margin:4px 0 5px;font-size:26px;color:#214c52}.nova-draw-hero p{margin:0;color:#648187}
.nova-draw-balance{min-width:210px;padding-left:24px;border-left:1px solid #b5dcd7;text-align:right}.nova-draw-balance span{display:block;color:#718c91;font-size:12px}.nova-draw-balance strong{display:block;margin-top:3px;color:#e57b2f;font-size:25px}
.nova-draw-main{display:grid;grid-template-columns:minmax(340px,1.05fr) minmax(310px,.95fr);gap:0;border:1px solid #cde6e3;border-top:0;background:#fff;border-radius:0 0 8px 8px;overflow:hidden}
.nova-draw-wheel-side{display:flex;align-items:center;justify-content:center;min-height:530px;padding:28px;background:#f7fcfb;border-right:1px solid #dceceb}
.nova-draw-wheel-shell{position:relative;width:min(100%,440px);aspect-ratio:1}.nova-draw-wheel-shell:before{content:"";position:absolute;inset:-10px;border:9px solid #fff;border-radius:50%;box-shadow:0 8px 32px rgba(20,92,91,.18),0 0 0 1px #b9deda;z-index:1;pointer-events:none}
#nova-draw-canvas{position:absolute;inset:0;width:100%;height:100%;border-radius:50%;transition:transform 5.2s cubic-bezier(.16,.7,.12,1);z-index:2}
.nova-draw-pointer{position:absolute;z-index:4;left:50%;top:-15px;transform:translateX(-50%);width:0;height:0;border-left:18px solid transparent;border-right:18px solid transparent;border-top:42px solid #ef8333;filter:drop-shadow(0 3px 2px rgba(90,54,20,.22))}
.nova-draw-hub{position:absolute;z-index:4;left:50%;top:50%;transform:translate(-50%,-50%);display:grid;place-items:center;width:82px;height:82px;border-radius:50%;background:#fff;border:7px solid #15968c;box-shadow:0 3px 14px rgba(16,90,87,.24);color:#e57c31;font-size:22px;font-weight:950}
.nova-draw-control{padding:30px 28px;display:flex;flex-direction:column;justify-content:center}.nova-draw-control h2{margin:0 0 7px!important;color:#24545a;font-size:21px!important}.nova-draw-control>p{margin:0 0 22px;color:#71888c;line-height:1.7}
.nova-draw-facts{display:grid;grid-template-columns:1fr 1fr;margin-bottom:20px;border:1px solid #d7e9e7;border-radius:7px;overflow:hidden}.nova-draw-facts div{padding:13px 14px}.nova-draw-facts div+div{border-left:1px solid #d7e9e7}.nova-draw-facts span{display:block;color:#7a9296;font-size:12px}.nova-draw-facts b{display:block;margin-top:4px;color:#167f77;font-size:17px}
.nova-draw-count-label{margin-bottom:9px;color:#587279;font-weight:800}.nova-draw-counts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0;margin-bottom:14px;border:1px solid #bddbd7;border-radius:7px;overflow:hidden;background:#f7fbfa}.nova-draw-counts button{display:flex!important;align-items:center;justify-content:center;box-sizing:border-box;min-width:0!important;width:100%!important;height:42px!important;margin:0!important;padding:0 8px!important;border:0!important;border-right:1px solid #d2e6e3!important;border-radius:0!important;background:#f7fbfa!important;box-shadow:none!important;color:#35676c!important;font-size:14px!important;font-weight:850!important;line-height:1!important;white-space:nowrap!important;word-break:keep-all!important;cursor:pointer}.nova-draw-counts button:last-child{border-right:0!important}.nova-draw-counts button:hover{background:#eaf7f5!important;color:#157e76!important}.nova-draw-counts button.is-active{background:#148f86!important;color:#fff!important}.nova-draw-counts button.is-active:hover{background:#148f86!important;color:#fff!important}
#nova-draw-submit{display:flex;align-items:center;justify-content:center;box-sizing:border-box;width:100%;height:48px!important;margin:0!important;padding:0 18px!important;border:0!important;border-radius:7px!important;background:#ed8334!important;box-shadow:0 7px 16px rgba(224,118,40,.2)!important;color:#fff!important;font-size:16px!important;font-weight:900!important;line-height:1!important;white-space:nowrap;cursor:pointer}#nova-draw-submit:hover{background:#df7427!important}#nova-draw-submit:disabled{background:#aab9b9!important;box-shadow:none!important;cursor:not-allowed}.nova-draw-cost{text-align:center;margin-top:11px;color:#7b9094}.nova-draw-cost b{color:#dc7130}
.nova-draw-section{margin-top:18px;background:#fff;border:1px solid #cde5e2;border-radius:8px;overflow:hidden}.nova-draw-section>header{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:#f0faf8;border-bottom:1px solid #d7ebe8}.nova-draw-section h2{margin:0!important;font-size:18px!important;color:#28565c}.nova-draw-section header span{color:#7a9397;font-size:12px}
.nova-draw-prizes{display:grid;grid-template-columns:repeat(4,1fr);gap:0}.nova-draw-prize{position:relative;min-height:92px;padding:16px;border-right:1px solid #edf3f2;border-bottom:1px solid #edf3f2}.nova-draw-prize:before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--prize)}.nova-draw-prize b{display:block;color:#315d62}.nova-draw-prize span{display:block;margin-top:7px;color:#829699;font-size:12px}
.nova-draw-columns{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px}.nova-draw-table{width:100%;border-collapse:collapse}.nova-draw-table th,.nova-draw-table td{padding:10px 13px;border-bottom:1px solid #edf3f2;text-align:left}.nova-draw-table th{color:#718b90;font-size:12px;background:#fbfdfd}.nova-draw-table td{color:#506d72}.nova-draw-table b{color:#16827a}.nova-draw-empty{text-align:center!important;padding:24px!important;color:#8aa0a4!important}
.nova-draw-admin{margin-top:18px;border:1px solid #e8d8c8;border-radius:8px;background:#fff}.nova-draw-admin>summary{padding:16px 18px;cursor:pointer;background:#fff7ef;color:#a65c25;font-weight:900}.nova-draw-admin-body{padding:18px}.nova-draw-settings{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding-bottom:18px;border-bottom:1px solid #e8eeee}.nova-draw-admin label{display:flex;flex-direction:column;gap:6px;color:#526f74;font-weight:750}.nova-draw-admin input,.nova-draw-admin select{box-sizing:border-box;width:100%;height:38px;padding:0 10px;border:1px solid #cbdedc;border-radius:5px;background:#fff;color:#34575c}.nova-draw-admin textarea{box-sizing:border-box;width:100%;min-height:64px;padding:8px 10px;border:1px solid #cbdedc;border-radius:5px;resize:vertical}.nova-draw-admin button{height:38px;padding:0 15px;border:0;border-radius:5px;background:#148f86;color:#fff;font-weight:850;cursor:pointer}.nova-draw-settings .nova-draw-admin-actions{justify-content:flex-end}.nova-draw-prize-admin{display:grid;grid-template-columns:1.2fr 1fr .8fr .8fr .7fr .7fr .7fr .6fr;gap:8px;align-items:end;padding:13px 0;border-bottom:1px solid #edf2f1}.nova-draw-prize-admin .wide{grid-column:span 2}.nova-draw-prize-admin .full{grid-column:1/-1}.nova-draw-prize-admin-actions{display:flex;gap:7px}.nova-draw-prize-admin-actions .delete{background:#bf5961}.nova-draw-add-title{margin:20px 0 4px;color:#27565c}
.nova-draw-result{position:fixed;z-index:10000;inset:0;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(21,55,57,.42)}.nova-draw-result.is-open{display:flex}.nova-draw-result-dialog{width:min(520px,100%);max-height:80vh;overflow:auto;background:#fff;border-radius:8px;box-shadow:0 24px 70px rgba(9,45,46,.3)}.nova-draw-result-dialog header{padding:20px 22px;background:#eaf9f7}.nova-draw-result-dialog h2{margin:0!important;color:#24545a}.nova-draw-result-list{padding:10px 22px}.nova-draw-result-list div{display:flex;justify-content:space-between;gap:14px;padding:11px 0;border-bottom:1px solid #edf2f1}.nova-draw-result-dialog footer{padding:14px 22px;text-align:center}.nova-draw-result-dialog button{padding:9px 24px;border:0;border-radius:6px;background:#148f86;color:#fff;font-weight:850;cursor:pointer}
@media(max-width:1350px){.nova-draw-page{width:92%}}@media(max-width:900px){.nova-draw-main{grid-template-columns:1fr}.nova-draw-wheel-side{min-height:auto;border-right:0;border-bottom:1px solid #dceceb}.nova-draw-wheel-shell{width:min(86vw,400px)}.nova-draw-prizes{grid-template-columns:repeat(2,1fr)}.nova-draw-columns{grid-template-columns:1fr}.nova-draw-settings{grid-template-columns:1fr 1fr}.nova-draw-prize-admin{grid-template-columns:1fr 1fr 1fr}.nova-draw-prize-admin .wide{grid-column:span 1}.nova-draw-prize-admin .full{grid-column:1/-1}}
@media(max-width:600px){.nova-draw-page{position:relative;left:calc((100vw - 100%) / 2);width:calc(100vw - 18px);max-width:calc(100vw - 18px);margin-left:9px;margin-right:9px}.nova-draw-hero{align-items:flex-start;flex-direction:column;padding:18px}.nova-draw-balance{width:100%;padding:12px 0 0;border-left:0;border-top:1px solid #b5dcd7;text-align:left}.nova-draw-wheel-side{padding:28px 16px}.nova-draw-control{padding:22px 16px}.nova-draw-settings,.nova-draw-prize-admin{grid-template-columns:1fr}.nova-draw-prize-admin .full{grid-column:auto}}
</style>

<main class="nova-draw-page">
  <section class="nova-draw-hero">
    <div><span class="nova-draw-kicker">NOVAHD · LUCKY DRAW</span><h1>幸运抽奖</h1><p>每一次转动都由服务器独立随机决定，奖励即时到账。</p></div>
    <div class="nova-draw-balance"><span>当前魔力</span><strong id="nova-draw-balance"><?php echo number_format((float)$CURUSER['seedbonus'], 1); ?></strong></div>
  </section>
  <section class="nova-draw-main">
    <div class="nova-draw-wheel-side">
      <div class="nova-draw-wheel-shell"><div class="nova-draw-pointer"></div><canvas id="nova-draw-canvas" width="900" height="900"></canvas><div class="nova-draw-hub">N</div></div>
    </div>
    <div class="nova-draw-control">
      <h2>选择抽奖次数</h2><p>奖品、权重与库存均由后台实时控制。多抽会一次完成并逐项发放。</p>
      <div class="nova-draw-facts"><div><span>单次消耗</span><b><?php echo number_format((float)$settings['cost_per_draw'], 1); ?> 魔力</b></div><div><span>今日免费剩余</span><b><?php echo $freeLeft; ?> 次</b></div></div>
      <div class="nova-draw-count-label">抽奖次数</div>
      <div class="nova-draw-counts">
        <?php foreach (nova_draw_multi_counts() as $index => $count): ?><button type="button" data-count="<?php echo $count; ?>" class="<?php echo $index === 0 ? 'is-active' : ''; ?>"><?php echo $count; ?> 次</button><?php endforeach; ?>
      </div>
      <button type="button" class="nova-draw-submit" id="nova-draw-submit" <?php echo !$activePrizes || ($settings['enabled'] ?? 'yes') !== 'yes' ? 'disabled' : ''; ?>>开始抽奖</button>
      <div class="nova-draw-cost">预计消耗 <b id="nova-draw-cost">0.0</b> 魔力</div>
    </div>
  </section>

  <section class="nova-draw-section"><header><h2>当前奖池</h2><span><?php echo count($activePrizes); ?> 项奖品</span></header><div class="nova-draw-prizes">
    <?php foreach ($activePrizes as $prize): ?><div class="nova-draw-prize" style="--prize:<?php echo htmlspecialchars($prize['color']); ?>"><b><?php echo htmlspecialchars($prize['name']); ?></b><span><?php echo htmlspecialchars($typeLabels[$prize['type']] ?? $prize['type']); ?> · <?php echo (int)$prize['stock'] < 0 ? '库存不限' : '库存 ' . (int)$prize['stock']; ?></span></div><?php endforeach; ?>
  </div></section>

  <div class="nova-draw-columns">
    <section class="nova-draw-section" style="margin-top:0"><header><h2>最近中奖</h2><span>全站动态</span></header><table class="nova-draw-table"><thead><tr><th>用户</th><th>奖励</th><th>时间</th></tr></thead><tbody>
      <?php if (!$winners): ?><tr><td colspan="3" class="nova-draw-empty">暂无中奖记录</td></tr><?php endif; ?>
      <?php foreach ($winners as $row): ?><tr><td><b><?php echo htmlspecialchars($row['username'] ?: ('UID ' . $row['user_id'])); ?></b></td><td><?php echo htmlspecialchars($row['result_text']); ?></td><td><?php echo htmlspecialchars(date('m-d H:i', strtotime($row['created_at']))); ?></td></tr><?php endforeach; ?>
    </tbody></table></section>
    <section class="nova-draw-section" style="margin-top:0"><header><h2>我的记录</h2><span>最近 30 次</span></header><table class="nova-draw-table"><thead><tr><th>结果</th><th>消耗</th><th>时间</th></tr></thead><tbody>
      <?php if (!$history): ?><tr><td colspan="3" class="nova-draw-empty">还没有抽奖记录</td></tr><?php endif; ?>
      <?php foreach ($history as $row): ?><tr><td><?php echo htmlspecialchars($row['result_text']); ?></td><td><?php echo number_format((float)$row['cost_bonus'], 1); ?></td><td><?php echo htmlspecialchars(date('m-d H:i', strtotime($row['created_at']))); ?></td></tr><?php endforeach; ?>
    </tbody></table></section>
  </div>

  <?php if ($isAdmin): ?>
  <details class="nova-draw-admin"><summary>管理员配置</summary><div class="nova-draw-admin-body">
    <form class="nova-draw-settings nova-draw-admin-form">
      <input type="hidden" name="action" value="settings"><input type="hidden" name="token" value="<?php echo nova_draw_token((int)$CURUSER['id'], 'admin'); ?>">
      <label>功能状态<select name="enabled"><option value="yes" <?php echo $settings['enabled'] === 'yes' ? 'selected' : ''; ?>>开启</option><option value="no" <?php echo $settings['enabled'] !== 'yes' ? 'selected' : ''; ?>>关闭</option></select></label>
      <label>单次消耗<input type="number" name="cost_per_draw" min="0" step="0.1" value="<?php echo htmlspecialchars($settings['cost_per_draw']); ?>"></label>
      <label>每日免费次数<input type="number" name="daily_free_draws" min="0" max="50" value="<?php echo (int)$settings['daily_free_draws']; ?>"></label>
      <label>最低用户等级<select name="minimum_class"><?php foreach (\App\Models\User::$classes as $class => $info): ?><option value="<?php echo $class; ?>" <?php echo (int)$settings['minimum_class'] === (int)$class ? 'selected' : ''; ?>><?php echo htmlspecialchars(get_user_class_name($class)); ?></option><?php endforeach; ?></select></label>
      <label>VIP 重复补偿<input type="number" name="vip_compensation" min="0" step="0.1" value="<?php echo htmlspecialchars($settings['vip_compensation']); ?>"></label>
      <label>道具重复补偿<input type="number" name="duplicate_compensation" min="0" step="0.1" value="<?php echo htmlspecialchars($settings['duplicate_compensation']); ?>"></label>
      <label>多抽档位<input type="text" name="multi_draw_counts" value="<?php echo htmlspecialchars($settings['multi_draw_counts']); ?>"></label>
      <label>首页公示数量<input type="number" name="homepage_winner_count" min="1" max="20" value="<?php echo (int)$settings['homepage_winner_count']; ?>"></label>
      <label class="nova-draw-admin-actions"><button type="submit">保存设置</button></label>
    </form>
    <h3 class="nova-draw-add-title">奖品管理</h3>
    <?php
      $renderPrize = static function (array $prize) use ($typeLabels, $medals): void {
          $id = (int)($prize['id'] ?? 0); ?>
      <form class="nova-draw-prize-admin nova-draw-admin-form">
        <input type="hidden" name="action" value="save_prize"><input type="hidden" name="token" value="<?php echo nova_draw_token((int)$GLOBALS['CURUSER']['id'], 'admin'); ?>"><input type="hidden" name="id" value="<?php echo $id; ?>">
        <label class="wide">名称<input name="name" maxlength="100" required value="<?php echo htmlspecialchars($prize['name'] ?? ''); ?>"></label>
        <label>类型<select name="type"><?php foreach ($typeLabels as $type => $label): ?><option value="<?php echo $type; ?>" <?php echo ($prize['type'] ?? '') === $type ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
        <label>数值<input type="number" name="value" min="0" value="<?php echo (int)($prize['value'] ?? 0); ?>"></label>
        <label>权重<input type="number" name="weight" min="0.001" step="0.001" value="<?php echo htmlspecialchars($prize['weight'] ?? '1'); ?>"></label>
        <label>库存<input type="number" name="stock" min="-1" value="<?php echo (int)($prize['stock'] ?? -1); ?>"></label>
        <label>顺序<input type="number" name="sort_order" min="0" value="<?php echo (int)($prize['sort_order'] ?? 0); ?>"></label>
        <label>颜色<input type="color" name="color" value="<?php echo htmlspecialchars($prize['color'] ?? '#15988d'); ?>"></label>
        <label>状态<select name="enabled"><option value="1" <?php echo (int)($prize['enabled'] ?? 1) === 1 ? 'selected' : ''; ?>>启用</option><option value="0" <?php echo (int)($prize['enabled'] ?? 1) !== 1 ? 'selected' : ''; ?>>停用</option></select></label>
        <label class="wide">勋章<select name="medal_id"><option value="0">非勋章奖品</option><?php foreach ($medals as $medal): ?><option value="<?php echo (int)$medal['id']; ?>" <?php echo (int)($prize['medal_id'] ?? 0) === (int)$medal['id'] ? 'selected' : ''; ?>>#<?php echo (int)$medal['id']; ?> <?php echo htmlspecialchars($medal['name']); ?></option><?php endforeach; ?></select></label>
        <label class="wide">说明<input name="description" maxlength="255" value="<?php echo htmlspecialchars($prize['description'] ?? ''); ?>"></label>
        <div class="nova-draw-prize-admin-actions"><button type="submit"><?php echo $id ? '保存' : '新增'; ?></button><?php if ($id): ?><button type="button" class="delete nova-draw-delete" data-id="<?php echo $id; ?>">删除</button><?php endif; ?></div>
      </form>
    <?php }; foreach ($prizes as $prize) { $renderPrize($prize); } $renderPrize([]); ?>
  </div></details>
  <?php endif; ?>
</main>

<div class="nova-draw-result" id="nova-draw-result"><div class="nova-draw-result-dialog"><header><h2>抽奖结果</h2></header><div class="nova-draw-result-list" id="nova-draw-result-list"></div><footer><button type="button" id="nova-draw-result-close">查看记录</button></footer></div></div>

<script>
(function(){
  const prizes=<?php echo json_encode($publicPrizes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const costPerDraw=<?php echo json_encode((float)$settings['cost_per_draw']); ?>,freeLeft=<?php echo $freeLeft; ?>;
  const spinToken=<?php echo json_encode(nova_draw_token((int)$CURUSER['id'], 'spin')); ?>;
  const canvas=document.getElementById('nova-draw-canvas'),ctx=canvas.getContext('2d');
  let selectedCount=<?php echo (int)(nova_draw_multi_counts()[0] ?? 1); ?>,rotation=0,busy=false,displayedBalance=<?php echo json_encode((float)$CURUSER['seedbonus']); ?>;
  function showBalance(value){displayedBalance=Number(value);const formatted=displayedBalance.toLocaleString(undefined,{minimumFractionDigits:1,maximumFractionDigits:1});document.getElementById('nova-draw-balance').textContent=formatted;document.querySelectorAll('[data-nova-header-bonus]').forEach(element=>{element.textContent=formatted;});}
  function draw(){
    const size=canvas.width,c=size/2,r=c-12;ctx.clearRect(0,0,size,size);
    if(!prizes.length){ctx.fillStyle='#dbe9e7';ctx.beginPath();ctx.arc(c,c,r,0,Math.PI*2);ctx.fill();return;}
    const arc=Math.PI*2/prizes.length;
    prizes.forEach((p,i)=>{const start=-Math.PI/2+i*arc,end=start+arc;ctx.beginPath();ctx.moveTo(c,c);ctx.arc(c,c,r,start,end);ctx.closePath();ctx.fillStyle=p.color||'#15988d';ctx.fill();ctx.strokeStyle='rgba(255,255,255,.72)';ctx.lineWidth=4;ctx.stroke();ctx.save();ctx.translate(c,c);ctx.rotate(start+arc/2);ctx.textAlign='right';ctx.fillStyle='#fff';ctx.font='800 '+Math.max(19,Math.min(29,260/prizes.length+12))+'px sans-serif';ctx.shadowColor='rgba(0,0,0,.18)';ctx.shadowBlur=3;const label=p.name.length>10?p.name.slice(0,9)+'…':p.name;ctx.fillText(label,r-34,8);ctx.restore();});
  }
  function updateCost(){const paid=Math.max(0,selectedCount-freeLeft),cost=paid*costPerDraw;document.getElementById('nova-draw-cost').textContent=cost.toLocaleString(undefined,{minimumFractionDigits:1,maximumFractionDigits:1});}
  document.querySelectorAll('.nova-draw-counts button').forEach(btn=>btn.addEventListener('click',()=>{if(busy)return;document.querySelectorAll('.nova-draw-counts button').forEach(x=>x.classList.remove('is-active'));btn.classList.add('is-active');selectedCount=Number(btn.dataset.count);updateCost();}));
  function openResults(data){const list=document.getElementById('nova-draw-result-list');list.innerHTML='';data.draws.forEach((draw,i)=>{const row=document.createElement('div'),left=document.createElement('b'),right=document.createElement('span');left.textContent='第 '+(i+1)+' 次 · '+draw.name;right.textContent=draw.result;row.append(left,right);list.appendChild(row);});document.getElementById('nova-draw-result').classList.add('is-open');showBalance(data.balance);}
  document.getElementById('nova-draw-submit').addEventListener('click',async function(){if(busy||!prizes.length)return;busy=true;this.disabled=true;this.textContent='抽奖进行中';const balanceBeforeDraw=displayedBalance,estimatedCost=Math.max(0,selectedCount-freeLeft)*costPerDraw;showBalance(Math.max(0,balanceBeforeDraw-estimatedCost));try{const body=new URLSearchParams({count:String(selectedCount),token:spinToken});const response=await fetch('fortune-wheel-spin.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});const data=await response.json();if(!data.success)throw new Error(data.message||'抽奖失败');showBalance(data.balance_after_cost);const last=data.draws[data.draws.length-1],index=Math.max(0,prizes.findIndex(p=>p.id===last.prize_id)),slice=360/prizes.length;rotation+=360*7+(360-(index*slice+slice/2))-(rotation%360);canvas.style.transform='rotate('+rotation+'deg)';setTimeout(()=>openResults(data),5300);}catch(error){showBalance(balanceBeforeDraw);alert(error.message);}finally{setTimeout(()=>{busy=false;this.disabled=false;this.textContent='开始抽奖';},5400);}});
  document.getElementById('nova-draw-result-close').addEventListener('click',()=>location.reload());document.getElementById('nova-draw-result').addEventListener('click',e=>{if(e.target.id==='nova-draw-result')location.reload();});
  document.querySelectorAll('.nova-draw-admin-form').forEach(form=>form.addEventListener('submit',async e=>{e.preventDefault();const button=form.querySelector('button[type=submit]'),old=button.textContent;button.disabled=true;button.textContent='保存中';try{const response=await fetch('fortune-wheel-admin.php',{method:'POST',body:new FormData(form)}),data=await response.json();if(!data.success)throw new Error(data.message);alert(data.message);location.reload();}catch(error){alert(error.message);}finally{button.disabled=false;button.textContent=old;}}));
  document.querySelectorAll('.nova-draw-delete').forEach(button=>button.addEventListener('click',async()=>{if(!confirm('确定删除这个奖品吗？历史记录会保留。'))return;const body=new FormData();body.set('action','delete_prize');body.set('id',button.dataset.id);body.set('token',<?php echo json_encode(nova_draw_token((int)$CURUSER['id'], 'admin')); ?>);const response=await fetch('fortune-wheel-admin.php',{method:'POST',body}),data=await response.json();alert(data.message);if(data.success)location.reload();}));
  draw();updateCost();
})();
</script>
<?php stdfoot(); ?>
