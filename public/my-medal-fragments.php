<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
require_once __DIR__ . '/../include/medal_fragments.php';
medal_fragment_ensure_tables();
$uid = (int)$CURUSER['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'merge') {
    $medalId = (int)($_POST['medal_id'] ?? 0);
    if (medal_fragment_user_owns($uid, $medalId)) stderr('勋章合成', '您已经拥有这枚勋章，碎片仍可继续收集，但不能重复佩戴，请前往交易所进行交换。');
    if (!medal_fragment_consume_complete_set($uid, $medalId)) stderr('勋章合成', '必须集齐五个不同部位后才能合成。');
    $medal = mysql_fetch_assoc(sql_query("SELECT id,duration,priority FROM medals WHERE id={$medalId} LIMIT 1"));
    if (!$medal) stderr('勋章合成', '勋章不存在。');
    $expire = $medal['duration'] ? sqlesc(date('Y-m-d H:i:s', time() + (int)$medal['duration'] * 86400)) : 'NULL';
    sql_query("INSERT INTO user_medals(uid,medal_id,expire_at,status,priority,created_at,updated_at) VALUES({$uid},{$medalId},{$expire},1,".(int)$medal['priority'].',NOW(),NOW()) ON DUPLICATE KEY UPDATE status=1') or sqlerr(__FILE__,__LINE__);
    header('Location: my-medal-fragments.php?merged=1');
    exit;
}

$rows = [];
$res = sql_query("SELECT m.id,m.name,m.image_large,COALESCE(f.amount,0) AS fragments,
    EXISTS(SELECT 1 FROM user_medals um WHERE um.uid={$uid} AND um.medal_id=m.id AND um.status=1 AND (um.expire_at IS NULL OR um.expire_at>=NOW())) AS owned
    FROM medals m INNER JOIN medal_fragment_config c ON c.medal_id=m.id AND c.enabled=1
    LEFT JOIN medal_fragments f ON f.medal_id=m.id AND f.uid={$uid}
    WHERE m.display_on_medal_page=1 ORDER BY m.priority DESC,m.id DESC");
while ($res && ($row = mysql_fetch_assoc($res))) $rows[] = $row;

stdhead('勋章合成');
begin_main_frame();
echo '<style>
.nova-fragment-wall{max-width:1180px;margin:18px auto 34px;color:#29424a}
.nova-fragment-head{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:18px 22px;border-radius:8px;background:linear-gradient(110deg,#123d48,#0b8c80);color:#fff}
.nova-fragment-head h1{margin:0 0 5px;color:#fff;font-size:24px}.nova-fragment-head p{margin:0;color:rgba(255,255,255,.78);font-size:13px}
.nova-fragment-draw{display:inline-block;padding:10px 16px;border-radius:6px;background:#fff1a8;color:#315057;text-decoration:none;font-weight:700}
.nova-fragment-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:16px}
.nova-fragment-card{position:relative;min-height:230px;padding:16px;border:1px solid #d7e5e5;border-radius:8px;background:#fff;text-align:center;box-shadow:0 5px 16px rgba(31,75,81,.07)}
.nova-fragment-card h3{margin:9px 0 6px;color:#24454d;font-size:15px}.nova-fragment-card p{margin:5px 0;color:#708589;font-size:12px}.nova-fragment-owned-note{display:inline-flex;margin-bottom:3px;padding:3px 8px;border-radius:10px;background:#fff4cf;color:#8b6814;font-size:11px;font-weight:700}
.nova-fragment-medal{position:relative;width:150px;height:118px;margin:0 auto}.nova-fragment-full{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;filter:grayscale(1);opacity:.2;transition:all .25s ease}.nova-fragment-part{position:absolute;inset:0;display:block;width:150px;height:118px;opacity:0;filter:none;overflow:hidden;transition:opacity .25s ease,filter .25s ease}.nova-fragment-part img{width:150px;height:118px;object-fit:contain}.nova-fragment-part b{display:none}.nova-fragment-part.filled{opacity:1;filter:drop-shadow(0 0 5px rgba(17,190,160,.48))}.part-1{clip-path:polygon(0 0,52% 0,44% 40%,0 49%)}.part-2{clip-path:polygon(52% 0,100% 0,100% 49%,60% 42%,44% 40%)}.part-3{clip-path:polygon(0 49%,44% 40%,47% 72%,36% 100%,0 100%)}.part-4{clip-path:polygon(44% 40%,60% 42%,68% 72%,62% 100%,36% 100%,47% 72%)}.part-5{clip-path:polygon(60% 42%,100% 49%,100% 100%,62% 100%,68% 72%)}.nova-fragment-card.is-complete .nova-fragment-full{filter:none;opacity:1}.nova-fragment-card.is-complete .nova-fragment-part{filter:none}
.nova-fragment-merge{display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;min-height:31px;padding:6px 13px;border:0;border-radius:5px;background:#0b9888;color:#fff!important;text-decoration:none;cursor:pointer}.nova-fragment-merge:disabled{background:#b8caca;cursor:not-allowed}.nova-fragment-trade{background:#c58a18}
@media(max-width:900px){.nova-fragment-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:620px){.nova-fragment-head{align-items:flex-start;flex-direction:column}.nova-fragment-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.nova-fragment-card{padding:10px;min-height:210px}.nova-fragment-medal{width:92px;height:92px}}
</style><div class="nova-fragment-wall"><div class="nova-fragment-head"><div><h1>勋章合成</h1><p>这里的碎片收集与勋章墙独立计算。已有勋章仍可继续收集，集齐五个不同部位后只能用于交易。</p></div><a class="nova-fragment-draw" href="medal-fragments.php">前往碎片中心</a></div><div class="nova-fragment-grid">';
foreach ($rows as $row) {
    $parts = medal_fragment_collection_parts($uid, (int)$row['id']);
    $amount = count(array_filter($parts, static fn (int $partAmount): bool => $partAmount > 0));
    $totalPieces = medal_fragment_total($uid, (int)$row['id']);
    $alreadyOwned = (bool)$row['owned'];
    $image = htmlspecialchars((string)$row['image_large'], ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8');
    $partHtml = '';
    foreach ($parts as $partNo => $partAmount) $partHtml .= '<span class="nova-fragment-part part-'.$partNo.' '.($partAmount > 0 ? 'filled' : '').'"><img src="'.$image.'" alt=""><b>部位 '.$partNo.($partAmount > 1 ? ' × '.$partAmount : '').'</b></span>';
    $ownedNote = $alreadyOwned ? '<span class="nova-fragment-owned-note">勋章墙已拥有</span>' : '';
    if ($amount >= 5 && $alreadyOwned) {
        $actionHtml = '<a class="nova-fragment-merge nova-fragment-trade" href="medal-exchange.php">仅可前往交易</a>';
    } else {
        $actionHtml = '<form method="post"><input type="hidden" name="action" value="merge"><input type="hidden" name="medal_id" value="'.(int)$row['id'].'"><button class="nova-fragment-merge" type="submit" '.($amount < 5 ? 'disabled' : '').'>'.($amount >= 5 ? '合成并佩戴' : '继续收集').'</button></form>';
    }
    echo '<article class="nova-fragment-card '.($amount >= 5 ? 'is-complete' : '').'"><div class="nova-fragment-medal"><img class="nova-fragment-full" src="'.$image.'" alt="'.$name.'">'.$partHtml.'</div><h3>'.$name.'</h3>'.$ownedNote.'<p>已收集部位：'.$amount.' / 5 · 碎片总数：'.$totalPieces.'</p>'.$actionHtml.'</article>';
}
if (!$rows) echo '<p>暂时没有开放合成的勋章。</p>';
echo '</div></div>';
end_main_frame();
stdfoot();
