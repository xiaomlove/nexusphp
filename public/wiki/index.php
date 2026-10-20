<?php
require_once __DIR__ . '/../../include/bittorrent.php';
dbconn();
$page = $_GET['page'] ?? 'home';
if (!in_array($page, ['home', 'rules', 'faq'], true)) $page = 'home';
function wh(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function wiki_clean_legacy(string $text, bool $title = false): string {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    for ($i = 0; $i < 3; $i++) {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded === $text) break;
        $text = $decoded;
    }
    $text = str_replace(["\u{00A0}", "\u{200B}", "\u{FEFF}", "\u{FFFD}"], [' ', '', '', ''], $text);
    $text = preg_replace('/<!--.*?-->/su', '', $text);
    $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1\s*>/isu', '', $text);
    $text = preg_replace_callback('/<a\b([^>]*)>(.*?)<\/a\s*>/isu', static function (array $match): string {
        $label = trim(strip_tags($match[2]));
        if (!preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/isu', $match[1], $hrefMatch)
            && !preg_match('/\bhref\s*=\s*([^\s>]+)/isu', $match[1], $hrefMatch)) {
            return $label;
        }
        $href = trim(html_entity_decode(end($hrefMatch), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '' || preg_match('/^(?:javascript|data):/iu', $href)) return $label;
        return '[url=' . str_replace([']', "\n"], ['', ''], $href) . ']' . $label . '[/url]';
    }, $text);
    $text = preg_replace_callback('/<img\b([^>]*)\/?\s*>/isu', static function (array $match): string {
        if (!preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/isu', $match[1], $srcMatch)
            && !preg_match('/\bsrc\s*=\s*([^\s>]+)/isu', $match[1], $srcMatch)) {
            return '';
        }
        $src = trim(html_entity_decode(end($srcMatch), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($src === '' || preg_match('/^(?:javascript|data):/iu', $src)) return '';
        return "\n[img]" . str_replace([']', "\n"], ['', ''], $src) . "[/img]\n";
    }, $text);
    $text = preg_replace('/<br\b[^>]*\/?\s*>/iu', "\n", $text);
    $text = preg_replace('/<hr\b[^>]*\/?\s*>/iu', "\n", $text);
    $text = preg_replace('/<li\b[^>]*>/iu', "\n- ", $text);
    $text = preg_replace('/<\/li\s*>/iu', "\n", $text);
    $text = preg_replace('/<\/?(?:ul|ol)\b[^>]*>/iu', "\n", $text);
    $text = preg_replace('/<b\b[^>]*>/iu', '[b]', $text);
    $text = preg_replace('/<\/b\s*>/iu', '[/b]', $text);
    $text = preg_replace('/<i\b[^>]*>/iu', '[i]', $text);
    $text = preg_replace('/<\/i\s*>/iu', '[/i]', $text);
    $text = preg_replace('/<tr\b[^>]*>/iu', "\n", $text);
    $text = preg_replace('/<\/tr\s*>/iu', "\n", $text);
    $text = preg_replace('/<td\b[^>]*>/iu', '', $text);
    $text = preg_replace('/<\/td\s*>/iu', '  ', $text);
    $text = preg_replace('/<\/?table\b[^>]*>/iu', "\n", $text);
    $text = strip_tags($text);
    $text = preg_replace('/<\s*\/?\s*[a-z][^>]*>/iu', '', $text);
    $text = preg_replace('/[ \t]+/u', ' ', $text);
    $text = preg_replace('/[ \t]*\n[ \t]*/u', "\n", $text);
    $text = preg_replace('/\n{3,}/u', "\n\n", $text);
    if ($title) {
        $text = preg_replace('/\[(?:\/?(?:b|i|u|s|font|color|size|url|img|list|quote|code)[^\]]*|\*)\]/iu', '', $text);
        $text = preg_replace('/^\s*[-–—•·]+\s*/u', '', $text);
        $text = preg_replace('/\s+[-–—]+\s+/u', '：', $text);
        $text = trim($text, " \t\n\r\0\x0B-–—•·");
    }
    return trim($text);
}
function wiki_lang(): int {
    $id = (int)get_guest_lang_id();
    return (int)get_single_value('language', 'rule_lang', 'WHERE id = ' . $id) ? $id : 6;
}
function wiki_rules(): array {
    $rows=[]; $r=sql_query('SELECT title,text FROM rules WHERE lang_id=' . wiki_lang() . ' ORDER BY id');
    while($r && ($row=mysql_fetch_assoc($r))) $rows[]=$row;
    return $rows;
}
function wiki_faq(): array {
    $out=[]; $id=wiki_lang();
    $r=sql_query("SELECT link_id,question FROM faq WHERE type='categ' AND lang_id={$id} AND flag!='0' ORDER BY id");
    while($r && ($row=mysql_fetch_assoc($r))) $out[(int)$row['link_id']]=['title'=>$row['question'],'items'=>[]];
    $r=sql_query("SELECT categ,question,answer FROM faq WHERE type='item' AND lang_id={$id} AND flag!='0' ORDER BY id");
    while($r && ($row=mysql_fetch_assoc($r))) if(isset($out[(int)$row['categ']])) $out[(int)$row['categ']]['items'][]=$row;
    return $out;
}
$titles=['home'=>'帮助中心','rules'=>'站点规则','faq'=>'常见问答'];
$rules=$page==='rules'?wiki_rules():[]; $faqs=$page==='faq'?wiki_faq():[];
?><!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>NovaHD <?= wh($titles[$page]) ?></title><style>
:root{--ink:#183f45;--line:#cfe5e2;--teal:#087a70;--dark:#0c2d39;--orange:#e9812d}*{box-sizing:border-box}body{margin:0;background:#f3fbfb;color:var(--ink);font:15px/1.7 Arial,"Microsoft YaHei",sans-serif}a{color:inherit;text-decoration:none}.top{background:var(--dark);border-bottom:3px solid #1dc8aa}.top>div,.hero>div,.layout{width:min(1240px,calc(100% - 32px));margin:auto}.top>div{min-height:68px;display:flex;align-items:center;justify-content:space-between;gap:16px}.brand{color:#fff;font-size:20px;font-weight:800}.mark{display:inline-grid;place-items:center;width:34px;height:34px;margin-right:9px;border-radius:7px;background:var(--orange)}nav{display:flex;gap:7px}nav a{padding:7px 11px;border-radius:5px;color:#d9f7f2}nav a:hover,nav .on{background:#155765;color:#fff}.hero{background:linear-gradient(115deg,#0c2d39,#096d68);color:#fff}.hero>div{padding:35px 0}.hero h1{margin:0;font-size:30px}.hero p{margin:4px 0 0;color:#d3f4ef}.layout{display:grid;grid-template-columns:220px minmax(0,1fr);gap:20px;margin-top:24px;margin-bottom:45px}.side,.card{border:1px solid var(--line);border-radius:8px;background:#fff;overflow:hidden}.side a{display:block;padding:11px 15px;border-top:1px solid #edf4f3}.side a:hover,.side .on{background:#eaf8f5;color:var(--teal);font-weight:700}.side b{display:block;padding:13px 15px;background:#eaf8f5}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.card{padding:19px}.card b{display:block;color:var(--teal);font-size:17px}.card p{color:#628084}.go{color:var(--orange);font-weight:700}.rule,.faq{margin-bottom:14px;border:1px solid var(--line);border-radius:8px;background:#fff;overflow:hidden}.rule h2,.faq h2{margin:0;padding:13px 17px;background:#eaf8f5;font-size:17px}.body,.item{padding:15px 18px}.item{border-top:1px solid #e4efed}.item h3{margin:0 0 7px;color:#0d6f69;font-size:16px}.empty{padding:28px;text-align:center;border:1px dashed var(--line);border-radius:8px;background:#fff;color:#789397}@media(max-width:800px){.layout{grid-template-columns:1fr}.grid{grid-template-columns:1fr}.side{display:flex;overflow:auto}.side b{display:none}.side a{white-space:nowrap;border:0}.top>div{min-height:58px}.hero h1{font-size:25px}}
</style><style>
body{background:#f6f7f8;color:#30343a}.top{position:sticky;top:0;z-index:30;background:#090909;border:0}.top>div{width:100%;padding:0 18px}.brand{min-width:240px}.mark{background:#e58b2f}.wiki-search{width:min(570px,42vw);height:42px;padding:0 18px;border:0;border-radius:23px;background:#242424;color:#fff;font-size:14px;outline:0}.top nav a{color:#ddd}.hero{background:#fff;color:#394047;border-bottom:1px solid #ddd}.hero>div{width:100%;padding:14px 30px}.hero h1{font-size:13px;font-weight:500}.hero p{display:none}.layout{width:100%;grid-template-columns:255px minmax(0,1fr) 250px;gap:0;margin:0;align-items:start}.side{position:sticky;top:68px;height:calc(100vh - 68px);overflow:auto;border:0;border-radius:0;background:#147dcc;color:#fff}.side b{background:#147dcc;color:#d9efff;padding:17px 18px}.side a{border:0;padding:10px 20px;color:#fff}.side a:hover,.side .on{background:#086bb8;color:#fff}.side .group{padding:16px 17px 7px;border-top:1px solid rgba(255,255,255,.16);color:#cfeaff;font-size:12px}.doc{padding:22px 28px 50px;min-width:0}.rule,.faq{border:0;border-radius:0;background:transparent;overflow:visible}.rule h2,.faq h2{padding:9px 0 5px;border-bottom:1px solid #aeb4b8;background:transparent;color:#32373c;font-size:22px}.body,.item{padding:6px 4px 12px}.body p{margin:9px 0}.toc{position:sticky;top:88px;margin:18px 16px 0 10px;padding:16px;border:1px solid #ddd;border-radius:5px;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.08)}.toc b{display:block;margin-bottom:8px;color:#147dcc;font-size:13px}.toc a{display:block;padding:7px 3px;color:#42484d;font-size:13px}.toc a:hover{color:#147dcc}.intro{margin-bottom:20px;padding:18px 20px;border-left:55px solid #59aae7;border-radius:7px;background:#dceefa;color:#075eaa}.intro strong{display:block;font-size:17px}.intro p{margin:3px 0}.grid .card{border-radius:5px}.faq .item{background:#fff;border:1px solid #e0e3e5;border-top:0}@media(max-width:980px){.layout{grid-template-columns:220px minmax(0,1fr)}.toc{display:none}.wiki-search{display:none}}@media(max-width:720px){.top nav{display:none}.brand{min-width:0}.layout{display:block}.side{position:static;height:auto;display:flex;background:#147dcc}.side .group{display:none}.doc{padding:18px 14px}.hero>div{padding:11px 16px}}
</style></head><body><header class="top"><div><a class="brand" href="/wiki/"><span class="mark">N</span>NovaHD Wiki</a><input class="wiki-search" type="search" placeholder="搜索规则与问答..." aria-label="搜索"><nav><a href="/">返回站点</a><a href="/wiki/?page=rules">规则</a><a href="/wiki/?page=faq">问答</a></nav></div></header><section class="hero"><div><h1>首页　/　<?= wh($titles[$page]) ?></h1><p>站点使用说明、规则与常见问题统一在这里查阅。</p></div></section><main class="layout"><aside class="side"><b>站点规则</b><a href="/wiki/"<?= $page==='home'?' class="on"':'' ?>>帮助首页</a><a href="/wiki/?page=rules"<?= $page==='rules'?' class="on"':'' ?>>总则</a><div class="group">账号相关</div><a href="/wiki/?page=rules#account">账号责任</a><a href="/wiki/?page=rules#download">下载与做种</a><div class="group">上传相关</div><a href="/wiki/?page=rules#publish">发布规范</a><a href="/wiki/?page=rules#official">官种与活动</a><div class="group">站点功能</div><a href="/wiki/?page=rules#bonus">魔力与优惠</a><a href="/wiki/?page=rules#community">社区规范</a><a href="/wiki/?page=rules#appeal">申诉与处理</a><div class="group">帮助</div><a href="/wiki/?page=faq"<?= $page==='faq'?' class="on"':'' ?>>常见问题</a></aside><section class="doc">
<?php if($page==='home'): ?><div class="grid"><article class="card"><b>站点规则</b><p>账号、发布、下载、分享与社区行为规范。</p><a class="go" href="/wiki/?page=rules">查看规则</a></article><article class="card"><b>常见问答</b><p>快速了解做种、上传、魔力值及日常使用问题。</p><a class="go" href="/wiki/?page=faq">查看问答</a></article><article class="card"><b>活动奖励</b><p>查看活动魔力值、上传量和助推奖励。</p><a class="go" href="/activity-rewards.php">进入活动奖励</a></article></div>
<?php elseif($page==='rules'): ?>
<div class="intro"><strong>欢迎来到 NovaHD</strong><p>请在使用站点前完整阅读各项规则。管理团队保留对未明确规定但损害站点安全、资源秩序或用户权益的行为进行处理的权利。</p></div>
<article class="rule" id="account"><h2>一、规则总则与账号责任</h2><div class="body"><p>NovaHD 是私有资源分享站点。注册、登录、下载、发布、评论、使用活动功能，即表示用户已阅读并同意遵守本站规则及管理团队的后续公告。</p><p>账号仅限本人使用。禁止出售、出租、出借、共享账号，禁止通过他人账号规避下载权限、考核、警告、封禁或其他限制。用户应自行保管密码、邀请链接和客户端密钥；因泄露造成的后果由账号持有人承担。</p><p>禁止以自动注册、撞库、脚本刷取、虚假身份、恶意占用资源等方式影响站点秩序。管理团队可根据日志、行为记录和违规影响对账号进行限制、警告、禁用或清理。</p></div></article>
<article class="rule" id="download"><h2>二、下载、做种与分享率</h2><div class="body"><p>下载前请确认本地磁盘空间、网络环境与客户端设置。完成下载后应保持任务做种，不得在未完成最低保种要求前随意删除文件、移动文件或停止客户端。</p><p>用户应合理维护分享率与长期做种记录。不得使用虚假上传、异常客户端、流量伪造、重复刷流、代理转发刷量或其他方式制造不真实的上传与下载数据。发现异常数据时，管理团队可回收异常上传量、修正数据并进行处罚。</p><p>资源文件应保持完整可校验。因改名、移动、删除或文件损坏导致无法做种时，请优先修复本地任务；如确需停止，请确保不会违反该资源的活动、优惠或保种要求。</p></div></article>
<article class="rule" id="publish"><h2>三、发布与资源信息规范</h2><div class="body"><p>发布资源前，请先搜索是否已有相同版本。已有资源时应优先做种、补种或按站内规范发布更高质量或明显不同版本，避免无意义重复。</p><p>标题、副标题、分类、分辨率、音轨、字幕、制作组、来源和 MediaInfo/BDInfo 必须与资源实际内容一致。不得使用误导性标题、夸大画质、伪造版本、错误季集信息或与内容无关的标签。</p><p>发布页简介应包含可验证的作品信息与必要技术资料。影视资源建议提供中文名、英文名、年份、地区、类型、主演、简介及豆瓣等权威信息链接；电视剧、综艺等应明确季数与集数范围。发布者应在收到审种修正通知后及时处理。</p><p>禁止发布违法违规、恶意程序、钓鱼文件、隐私数据或其他危害用户设备与站点安全的内容。资源内发现问题时，发布者应配合修正或下架。</p></div></article>
<article class="rule" id="official"><h2>四、官种、保种区与活动资源</h2><div class="body"><p>带有官种标识的资源由站点统一管理，可能进入保种区、官种魔力值奖励、上传量奖励或新官种助推活动。活动资格、优惠状态、奖励结算与退出条件以资源页面和活动奖励页面的实时状态为准。</p><p>上传量奖励活动中，设 S 为活动官种的资源体积，d 为用户对该资源的连续有效保种天数，累计上传量奖励为：d＜15 时 U(d,S)=0；15≤d＜30 时 U(d,S)=20%×S；d≥30 时 U(d,S)=50%×S。系统在满15天时发放20%×S，满30天时再补发30%×S，两档合计为资源体积的50%。活动期间断开保种超过48小时，连续保种时间将重新计算。</p><p>官种魔力值奖励面向所有符合条件的参与者。设活动官种贡献量为 A<sub>e</sub>，按普通魔力曲线得到活动基础值 B<sub>e</sub>，由存活时间、做种人数和体积计算官种综合加成 P，则官种活动独立魔力的每小时计算基准 C = B<sub>e</sub> × P/100 × F。公式中的 F 为站点普通魔力全局基础系数。只有累计有效做种满 24 小时的活动官种参与计算，所有符合条件的用户均使用同一公式。</p><p><strong>魔力发放方式：</strong>系统每小时自动读取用户当时的有效做种状态，计算官种活动独立魔力并直接计入个人魔力余额，同时写入活动魔力值明细，无需手动领取。同一用户同一小时只发放一次；普通魔力由站点原有魔力系统独立计算和自动发放，活动系统不会重复发放普通魔力。</p><p>新官种助推活动中，设 Q<sub>j</sub>=max(U<sub>j,end</sub>-U<sub>j,start</sub>,0) 为用户 j 在48小时内的有效上传贡献，W 为 Q<sub>j</sub>＞0且排名前10的用户集合。获奖用户所得魔力为 R<sub>j</sub>=3000×Q<sub>j</sub>/Σ<sub>k∈W</sub>Q<sub>k</sub>；未进入 W 的用户本轮奖励为0。活动结束后系统自动结算，奖励直接计入个人魔力余额并发送站内通知。不得通过异常上传、断连重连、虚假客户端等方式干扰活动排名。</p></div></article>
<article class="rule" id="bonus"><h2>五、魔力值、优惠与站点奖励</h2><div class="body"><p>魔力值可由正常做种、签到、发布、字幕、论坛互动及站点活动等渠道获得。不同渠道的规则、上限、结算时间与可见范围可能不同，请以对应功能页面的说明为准。</p><p>优惠、免费、2X 等状态仅在页面标示的有效期内生效。活动结束、资源退出活动区或优惠到期后，资源将恢复至进入活动前的原有状态。用户不得利用缓存页面、异常请求或客户端漏洞规避下载量计算。</p><p>幸运抽奖、兑换、捐赠及其他消耗型功能均以系统记录为准。如发现页面显示与实际账目不一致，应通过站内渠道提交截图和时间信息。</p></div></article>
<article class="rule" id="community"><h2>六、社区、论坛与沟通规范</h2><div class="body"><p>请保持友善、克制和基于事实的沟通。禁止辱骂、人身攻击、歧视、引战、恶意举报、刷屏、广告、诈骗、钓鱼、传播隐私或煽动站内对立。</p><p>论坛主题、评论、字幕说明和资源简介应与对应版块有关。禁止发布无意义灌水、重复内容、推广外站、交易账号或诱导站外付费。</p><p>对管理决定、审核结果或技术问题有疑问时，请使用站内消息、报告或指定反馈渠道说明情况。</p></div></article>
<article class="rule" id="appeal"><h2>七、违规处理与申诉</h2><div class="body"><p>管理团队会综合违规性质、主观恶意、影响范围、历史记录和整改情况处理。处理措施包括提醒、编辑修正、删除内容、回收奖励、限制功能、警告、禁用及永久封禁。</p><p>技术异常、误判或需要补充证据时，用户可在合理期限内提交申诉。申诉应提供账号、相关资源、发生时间、客户端或页面截图及清晰说明。</p><p>规则未尽事项由管理团队根据站点安全、资源秩序和社区利益处理。重大规则调整将通过公告、站内消息或本帮助中心更新。</p></div></article>
<?php foreach($rules as $r): ?><article class="rule"><h2><?= wh(wiki_clean_legacy($r['title'], true)) ?></h2><div class="body"><?= format_comment(wiki_clean_legacy($r['text'])) ?></div></article><?php endforeach; ?>
<?php else: ?>
<section class="faq"><h2>账号与使用</h2><article class="item"><h3>为什么不能登录、下载或使用某项功能？</h3><div>请先检查账号状态、等级要求、分享率、警告、活动资格和页面提示。不要重复刷新或使用多个设备绕过限制；仍无法解决时，请记录提示内容和发生时间后提交站内反馈。</div></article><article class="item"><h3>可以把账号交给朋友使用吗？</h3><div>不可以。账号只限本人使用，账号共享会造成安全风险和数据混乱，也可能导致限制或封禁。</div></article></section>
<section class="faq"><h2>下载、做种与上传</h2><article class="item"><h3>下载完成后多久可以停止做种？</h3><div>请遵守资源页面、站点考核和活动要求。普通资源建议长期保种；活动资源还需要满足连续做种时长，提前停止可能失去奖励或重新计算进度。</div></article><article class="item"><h3>为什么活动上传量还没有到账？</h3><div>上传量奖励仅统计已达到对应档位并完成结算的记录。连续保种未满 15 天、断种超过 48 小时重新计时或尚未到结算周期时，均不会立即显示为已获奖励。</div></article></section>
<section class="faq"><h2>官种与活动奖励</h2><article class="item"><h3>活动魔力值和正常魔力值有什么区别？</h3><div>普通魔力由站点原有魔力系统自动计算和发放；官种活动魔力由活动系统按活动公式独立计算，活动系统只发放活动增量，不会重复发放普通魔力。两类记录在活动奖励页面中分开统计。</div></article><article class="item"><h3>助推奖励如何产生？</h3><div>新官种助推活动结束后，系统按活动期间的有效上传贡献进行排名和结算。奖励记录会同步到活动奖励和个人“我的活动奖励”总表。</div></article></section>
<?php foreach($faqs as $f): ?><section class="faq"><h2><?= wh(wiki_clean_legacy($f['title'], true)) ?></h2><?php foreach($f['items'] as $i): ?><article class="item"><h3><?= wh(wiki_clean_legacy($i['question'], true)) ?></h3><div><?= format_comment(wiki_clean_legacy($i['answer'])) ?></div></article><?php endforeach; ?></section><?php endforeach; ?><?php endif; ?>
</section><aside class="toc"><b>页面内容</b><?php if($page==='rules'): ?><a href="#account">账号责任</a><a href="#download">下载与做种</a><a href="#publish">发布规范</a><a href="#official">官种与活动</a><a href="#bonus">魔力与优惠</a><a href="#community">社区规范</a><a href="#appeal">申诉与处理</a><?php elseif($page==='faq'): ?><a href="/wiki/?page=faq">账号与使用</a><a href="/wiki/?page=faq">下载与做种</a><a href="/wiki/?page=faq">官种与活动奖励</a><?php else: ?><a href="/wiki/?page=rules">站点规则</a><a href="/wiki/?page=faq">常见问答</a><a href="/activity-rewards.php">活动奖励</a><?php endif; ?></aside></main></body></html>
