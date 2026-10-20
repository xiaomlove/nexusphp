<?php
require_once(__DIR__ . '/../include/bittorrent.php');
require_once(__DIR__ . '/../include/torrent_review_assistant.php');
require_once(__DIR__ . '/../include/nova_sticky_promotion.php');
loggedinorreturn();
if (!nova_permission_can_manage()) { stderr("Access denied", "Supervisors and administrators only."); }
$id = max(0, intval($_GET["id"] ?? 0));
$torrent = null;
if ($id) {
    $r = sql_query("SELECT * FROM torrents WHERE id = " . $id . " LIMIT 1");
    $torrent = $r ? mysql_fetch_assoc($r) : null;
}
stdhead("管理插件");
?>
<main style="max-width:1180px;margin:28px auto;padding:0 16px">
<h1>管理插件</h1>
<form method="get" style="margin:16px 0"><input name="id" value="<?php echo $id ?: ""; ?>" placeholder="Torrent ID" style="padding:9px;width:220px"><button type="submit" style="padding:9px 16px">Open</button></form>
<style>
.nova-plugin-overview{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:18px 0 24px}
.nova-plugin-card{padding:18px;border:1px solid #b7e3dc;border-radius:8px;background:linear-gradient(135deg,#f0fdfa,#fff7ed);box-shadow:0 6px 18px rgba(13,78,74,.08)}
.nova-plugin-card h2{margin:0 0 8px;color:#0f766e;font-size:18px}.nova-plugin-card p{margin:0;color:#64748b;font-size:13px;line-height:1.6}.nova-plugin-card small{display:inline-block;margin-top:12px;color:#047857;font-weight:700}
@media(max-width:800px){.nova-plugin-overview{grid-template-columns:1fr}}
</style>
<section class="nova-plugin-overview" aria-label="插件列表">
<article class="nova-plugin-card"><h2>审种助手</h2><p>检查种子分类、简介、技术信息和媒体资料，辅助审核并发送整改通知。</p><small><?php echo $torrent ? '已加载当前种子' : '输入 Torrent ID 后使用'; ?></small></article>
</section>
<?php if ($torrent): ?>
<p><strong>Selected:</strong> <?php echo htmlspecialchars($torrent["name"], ENT_QUOTES, "UTF-8"); ?> (#<?php echo $id; ?>)</p>
<section class="nova-plugin-manager-card">
<?php nova_render_torrent_review_assistant($torrent); ?>
</section>
<table width="100%" cellspacing="0" cellpadding="0"><tbody>
<?php nova_sticky_render_detail($torrent, $CURUSER); ?>
<?php nova_permission_render_detail($torrent, $CURUSER); ?>
</tbody></table>
<?php else: ?><p>请选择一个 Torrent ID，以加载上述插件的具体管理面板。</p><?php endif; ?>
</main>
<?php stdfoot(); ?>
