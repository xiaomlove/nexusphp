<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
if ($enabledonation != 'yes')
	stderr($lang_donate['std_sorry'], $lang_donate['std_do_not_accept_donation']);

$do = $_GET['do'] ?? '';
$canEditDonation = get_user_class() >= UC_STAFFLEADER;
$donationPublicEnabled = (string)\App\Models\Setting::getByName('misc.donation_public_enabled', 'yes') === 'yes';
if (!$donationPublicEnabled && !$canEditDonation) {
	stderr($lang_donate['std_sorry'], '捐赠功能当前未开放。');
}
$donationEditToken = hash_hmac(
	'sha256',
	'nova-donation-edit|' . (int)$CURUSER['id'],
	(string)config('app.key')
);

function nova_donation_inline(string $text): string
{
	$text = htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
	$text = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text);
	$text = preg_replace('/\[b\](.+?)\[\/b\]/iu', '<strong>$1</strong>', $text);
	$text = preg_replace('/\[i\](.+?)\[\/i\]/iu', '<em>$1</em>', $text);
	return $text;
}

function nova_donation_render_block(string $block, int $index): string
{
	$lines = preg_split('/\R/u', trim($block));
	$html = '<section class="nova-donation-language-panel' . ($index === 0 ? ' is-active' : '') . '" data-language-panel="' . $index . '">';
	$packageGridOpen = false;
	for ($i = 0, $count = count($lines); $i < $count; $i++) {
		$line = trim($lines[$i]);
		if ($line === '') {
			continue;
		}
		if (preg_match('/^#\s+(.+)$/u', $line, $match)) {
			if ($packageGridOpen) { $html .= '</div>'; $packageGridOpen = false; }
			$html .= '<h2 class="nova-donation-language-title">' . nova_donation_inline($match[1]) . '</h2>';
			continue;
		}
		if (preg_match('/^##\s+(.+)$/u', $line, $match)) {
			if ($packageGridOpen) { $html .= '</div>'; $packageGridOpen = false; }
			$html .= '<h3 class="nova-donation-section-title">' . nova_donation_inline($match[1]) . '</h3>';
			continue;
		}
		if (preg_match('/^(套餐\s*\d+|Package\s*\d+)\s*[：:]\s*(.+)$/iu', $line, $match)) {
			if (!$packageGridOpen) { $html .= '<div class="nova-donation-package-grid">'; $packageGridOpen = true; }
			$price = '';
			for ($next = $i + 1; $next < $count; $next++) {
				$nextLine = trim($lines[$next]);
				if ($nextLine === '') { continue; }
				if (preg_match('/^(特惠价|特惠價|Special Price)\s*[：:]\s*(.+)$/iu', $nextLine, $priceMatch)) {
					$price = '<div class="nova-donation-package-price"><span>' . nova_donation_inline($priceMatch[1]) . '</span><strong>' . nova_donation_inline($priceMatch[2]) . '</strong></div>';
					$i = $next;
				}
				break;
			}
			$html .= '<article class="nova-donation-package"><div class="nova-donation-package-name">' . nova_donation_inline($match[1]) . '</div><p>' . nova_donation_inline($match[2]) . '</p>' . $price . '</article>';
			continue;
		}
		if ($packageGridOpen) { $html .= '</div>'; $packageGridOpen = false; }
		if (preg_match('/^(.+?)[：:]\s*(特惠价|特惠價|Special Price)\s*(.+)$/iu', $line, $match)) {
			$html .= '<div class="nova-donation-prop"><span>' . nova_donation_inline($match[1]) . '</span><b>' . nova_donation_inline($match[2] . ' ' . $match[3]) . '</b></div>';
			continue;
		}
		if (preg_match('/^(QQ|站内私信|站內私訊|In-Site Private Message)\s*[：:]\s*(.+)$/iu', $line, $match)) {
			$html .= '<div class="nova-donation-contact-row"><span>' . nova_donation_inline($match[1]) . '</span><strong>' . nova_donation_inline($match[2]) . '</strong></div>';
			continue;
		}
		$html .= '<p class="nova-donation-paragraph">' . nova_donation_inline($line) . '</p>';
	}
	if ($packageGridOpen) { $html .= '</div>'; }
	return $html . '</section>';
}

function nova_donation_render_details(string $content): string
{
	$blocks = preg_split('/^\s*---\s*$/mu', trim($content));
	$blocks = array_values(array_filter(array_map('trim', $blocks), static fn (string $block) => $block !== ''));
	if (!$blocks) {
		return '';
	}
	$labels = ['简体中文', '繁體中文', 'English'];
	$html = '<div class="nova-donation-details"><div class="nova-donation-language-tabs" role="tablist">';
	foreach ($blocks as $index => $_) {
		$label = $labels[$index] ?? ('Language ' . ($index + 1));
		$html .= '<button type="button" role="tab" data-language-tab="' . $index . '" class="' . ($index === 0 ? 'is-active' : '') . '"><span>' . htmlspecialchars($label) . '</span></button>';
	}
	$html .= '</div>';
	foreach ($blocks as $index => $block) {
		$html .= nova_donation_render_block($block, $index);
	}
	return $html . '</div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['donation_action'] ?? '') === 'toggle_visibility') {
	if (!$canEditDonation) {
		stderr('权限不足', '仅主管账号可以操作捐赠开关。');
	}
	if (!hash_equals($donationEditToken, (string)($_POST['token'] ?? ''))) {
		stderr('请求无效', '页面令牌已失效，请返回捐赠页面后重试。');
	}
	$donationPublicEnabled = (string)($_POST['donation_public_enabled'] ?? '') === 'yes';
	\App\Models\Setting::query()->upsert([
		['name' => 'misc.donation_public_enabled', 'value' => $donationPublicEnabled ? 'yes' : 'no', 'autoload' => 'no'],
	], ['name'], ['value', 'autoload', 'updated_at']);
	$navigationPermission = $donationPublicEnabled ? 'NULL' : (string)UC_STAFFLEADER;
	sql_query("UPDATE navigations SET permission = {$navigationPermission}, updated_at = NOW() WHERE url = 'donate.php'") or sqlerr();
	clear_setting_cache();
	header('Location: donate.php?visibility=1&edit=1');
	die;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['donation_action'] ?? '') === 'save') {
	if (!$canEditDonation) {
		stderr('权限不足', '仅主管账号可以编辑捐赠信息。');
	}
	if (!hash_equals($donationEditToken, (string)($_POST['token'] ?? ''))) {
		stderr('请求无效', '页面令牌已失效，请返回捐赠页面后重试。');
	}

	$details = trim((string)($_POST['donation_details'] ?? ''));
	$qrUrl = trim((string)($_POST['donation_alipay_qr_url'] ?? ''));
	if (mb_strlen($details, 'UTF-8') > 20000) {
		stderr('内容过长', '捐赠详情不能超过 20000 个字符。');
	}
	if ($qrUrl !== '' && (!filter_var($qrUrl, FILTER_VALIDATE_URL) || parse_url($qrUrl, PHP_URL_SCHEME) !== 'https')) {
		stderr('图片地址错误', '支付宝二维码必须使用有效的 HTTPS 图片地址。');
	}

	$settings = [
		['name' => 'misc.donation_custom', 'value' => $details, 'autoload' => 'no'],
		['name' => 'misc.donation_alipay_qr_url', 'value' => $qrUrl, 'autoload' => 'no'],
	];
	\App\Models\Setting::query()->upsert($settings, ['name'], ['value', 'autoload', 'updated_at']);
	clear_setting_cache();
	header('Location: donate.php?saved=1');
	die;
}

if ($do == 'thanks') {
	stderr($lang_donate['std_success'], $lang_donate['std_donation_success_note_one']."<a href=\"sendmessage.php?receiver=".$ACCOUNTANTID."\"><b>".$lang_donate['std_here']."</b></a>".$lang_donate['std_donation_success_note_two'], false);
}
else
{
    $custom = trim((string)\App\Models\Setting::getByName('misc.donation_custom', ''));
	$paypal = safe_email($PAYPALACCOUNT);
	if ($paypal && check_email($paypal))
		$showpaypal = true;
	else
		$showpaypal = false;
	$alipay = safe_email($ALIPAYACCOUNT);
	if ($alipay && (check_email($alipay) || preg_match('/^\+?[0-9 -]{6,20}$/', $alipay)))
		$showalipay = true;
	else
		$showalipay = false;
	$alipayQrUrl = trim((string)\App\Models\Setting::getByName('misc.donation_alipay_qr_url', ''));
	$showAlipayQr = filter_var($alipayQrUrl, FILTER_VALIDATE_URL)
		&& parse_url($alipayQrUrl, PHP_URL_SCHEME) === 'https';
	$alipayQrDisplay = $showAlipayQr ? htmlspecialchars($alipayQrUrl, ENT_QUOTES, 'UTF-8') : '';

	if ($showpaypal && $showalipay)
		$tdattr = "width=\"50%\"";
	elseif ($showpaypal || $showalipay)
		$tdattr = "colspan=\"2\" width=\"100%\"";

	if (!$showpaypal && !$showalipay && !$custom) {
        stderr($lang_donate['std_error'], $lang_donate['std_no_donation_account_available'], false);
    }

	stdhead($lang_donate['head_donation']);
	begin_main_frame();
	echo '<style>
	.nova-donation-page{box-sizing:border-box;width:65%;max-width:calc(100vw - 18px);margin:18px auto 34px;color:#294f55;letter-spacing:0}
	.nova-donation-heading{display:flex;align-items:center;justify-content:space-between;padding:20px 24px;background:#eaf9f7;border:1px solid #c5e5e0;border-radius:8px 8px 0 0}
	.nova-donation-heading small{display:block;color:#0e8e82;font-size:11px;font-weight:900;letter-spacing:1.5px}.nova-donation-title{margin:4px 0 0!important;color:#214d53!important;font-size:25px!important;line-height:1.2!important;text-align:left!important}.nova-donation-title:before,.nova-donation-title:after{display:none!important;content:none!important}
	.nova-donation-heading-mark{display:grid;place-items:center;width:46px;height:46px;border-radius:7px;background:#ed8434;color:#fff;font-size:24px;font-weight:950;box-shadow:0 7px 16px rgba(224,118,40,.18)}
	.nova-donation-table{box-sizing:border-box;width:100%!important;margin:0 auto;border-collapse:separate!important;border-spacing:0 12px!important}
	.nova-donation-table td.text{padding:20px!important;border:1px solid rgba(15,118,110,.14)!important;border-radius:7px!important;background:#fff!important;box-shadow:0 8px 22px rgba(15,35,45,.06)!important;color:#254653!important;line-height:1.75!important}
	.nova-donation-table tr:first-child td.text{padding:14px 20px!important;border-top:0!important;border-radius:0 0 7px 7px!important;background:#f8fcfb!important;box-shadow:none!important;color:#526f74!important}
	.nova-donation-custom{padding-top:0!important}.nova-donation-details{overflow:hidden}.nova-donation-language-tabs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0;margin:0 -20px 20px;border-bottom:1px solid #d8e9e6;background:#f5faf9}.nova-donation-language-tabs button{display:flex!important;align-items:center!important;justify-content:center!important;box-sizing:border-box;width:100%!important;height:46px!important;min-height:46px!important;margin:0!important;padding:0!important;overflow:visible!important;border:0!important;border-right:1px solid #d8e9e6!important;border-radius:0!important;background:#f5faf9!important;box-shadow:none!important;color:#557277!important;font-family:"Microsoft YaHei","PingFang SC",Arial,sans-serif!important;font-size:14px!important;font-weight:850!important;line-height:1!important;text-align:center!important;text-indent:0!important;white-space:nowrap!important;cursor:pointer}.nova-donation-language-tabs button>span{display:flex!important;align-items:center!important;justify-content:center!important;width:100%!important;height:22px!important;margin:0!important;padding:0!important;overflow:visible!important;font:inherit!important;line-height:22px!important;text-align:center!important;white-space:nowrap!important;transform:none!important}.nova-donation-language-tabs button:last-child{border-right:0!important}.nova-donation-language-tabs button.is-active{background:#118f84!important;color:#fff!important}
	.nova-donation-language-panel{display:none}.nova-donation-language-panel.is-active{display:block}.nova-donation-language-title{margin:0 0 7px!important;color:#214e54!important;font-size:22px!important}.nova-donation-language-title:before,.nova-donation-language-title:after,.nova-donation-section-title:before,.nova-donation-section-title:after{display:none!important;content:none!important}.nova-donation-section-title{margin:22px 0 11px!important;padding:9px 12px!important;border-radius:6px!important;background:#edf8f6!important;color:#18786f!important;font-size:16px!important;line-height:1.4!important}.nova-donation-paragraph{margin:8px 0;color:#526f74;line-height:1.75}.nova-donation-paragraph strong{color:#e0782f}
	.nova-donation-package-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.nova-donation-package{display:flex;flex-direction:column;min-height:184px;padding:16px;border:1px solid #cee5e1;border-top:4px solid #15988d;border-radius:7px;background:#fff;box-shadow:0 7px 18px rgba(21,89,88,.06)}.nova-donation-package:nth-child(2){border-top-color:#3188a6}.nova-donation-package:nth-child(3){border-top-color:#ed8434}.nova-donation-package-name{color:#1d5d60;font-size:16px;font-weight:900}.nova-donation-package p{flex:1;margin:10px 0;color:#557176;line-height:1.7}.nova-donation-package-price{display:flex;align-items:end;justify-content:space-between;gap:8px;padding-top:10px;border-top:1px solid #e7f0ef}.nova-donation-package-price span{color:#829597;font-size:12px}.nova-donation-package-price strong{color:#e5792e;font-size:20px}
	.nova-donation-prop,.nova-donation-contact-row{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:11px 13px;border-bottom:1px solid #e8f0ef}.nova-donation-prop:last-of-type{border-bottom:0}.nova-donation-prop span,.nova-donation-contact-row span{color:#496b70;font-weight:750}.nova-donation-prop b{color:#df772e}.nova-donation-contact-row{margin-top:7px;border:1px solid #d4e8e5;border-radius:6px;background:#f7fbfa}.nova-donation-contact-row strong{color:#13847b}
	.nova-donation-qr{display:flex;align-items:center;justify-content:center;margin:18px 0;padding:18px;border:1px solid rgba(15,118,110,.18);border-radius:8px;background:#effcf8}
	.nova-donation-qr img{display:block;width:min(280px,80vw);height:auto;border-radius:8px;background:#fff;padding:10px;box-shadow:0 8px 24px rgba(15,35,45,.12)}
	.nova-donation-pending{margin:18px 0;padding:18px;border:1px dashed rgba(15,118,110,.3);border-radius:8px;background:#effcf8;text-align:center;color:#45616d;font-weight:700}
	.nova-donation-contact{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 18px;border:0;border-radius:8px;background:#0f9f8d;color:#fff!important;font-weight:800;cursor:pointer;text-decoration:none!important;box-shadow:0 7px 18px rgba(15,159,141,.2)}
	.nova-donation-contact:hover{background:#0b8275}
	.nova-donation-actions{text-align:center;margin:12px 0}
	.nova-donation-note{margin:12px 0 0;color:#587084}
	.nova-donation-saved{margin:12px 0 0;padding:12px 16px;border:1px solid rgba(16,185,129,.32);border-radius:7px;background:#ecfdf5;color:#047857;font-weight:800;text-align:center}
	.nova-donation-manage{margin:18px auto 0;padding:20px;border:1px solid rgba(15,118,110,.18);border-radius:7px;background:#fff;box-shadow:0 8px 22px rgba(15,35,45,.06)}
	.nova-donation-manage summary{display:flex;align-items:center;justify-content:center;min-height:40px;color:#0f766e;font-weight:850;cursor:pointer;list-style:none}
	.nova-donation-manage summary::-webkit-details-marker{display:none}
	.nova-donation-editor{display:grid;gap:14px;margin-top:18px}
	.nova-donation-field{display:grid;gap:7px}
	.nova-donation-field label{color:#234653;font-weight:800}
	.nova-donation-field input,.nova-donation-field textarea{box-sizing:border-box;width:100%!important}
	.nova-donation-field textarea{min-height:230px;resize:vertical}
	.nova-donation-help{color:#647985;font-size:12px}
	.nova-donation-save{display:flex;justify-content:center;padding-top:4px}
	.nova-donation-save button{min-width:128px;min-height:40px;padding:0 22px;border-radius:8px!important}
	.nova-donation-visibility{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:16px;padding:13px 15px;border:1px solid #d4e8e5;border-radius:7px;background:#f7fbfa}.nova-donation-visibility-copy{display:grid;gap:3px}.nova-donation-visibility-copy strong{color:#234653}.nova-donation-visibility-copy span{color:#647985;font-size:12px}.nova-donation-visibility button{min-width:92px;min-height:36px;border-radius:6px!important}.nova-donation-visibility.is-on{border-color:rgba(16,185,129,.30);background:#ecfdf5}.nova-donation-visibility.is-on .nova-donation-visibility-copy strong{color:#07805f}.nova-donation-visibility.is-off{border-color:rgba(239,68,68,.25);background:#fff7f7}.nova-donation-visibility.is-off .nova-donation-visibility-copy strong{color:#c0263e}
	@media(max-width:1300px){.nova-donation-page{width:92%}}
	@media(max-width:760px){.nova-donation-page{position:relative;left:calc((100vw - 100%) / 2);width:calc(100vw - 18px);max-width:calc(100vw - 18px);margin-left:9px;margin-right:9px}.nova-donation-heading{padding:17px}.nova-donation-table{width:100%!important}.nova-donation-table td.text{display:block;width:auto!important;padding:15px!important}.nova-donation-table td.nova-donation-custom{padding-top:0!important}.nova-donation-language-tabs{margin:0 -15px 16px}.nova-donation-package-grid{grid-template-columns:1fr}.nova-donation-package{min-height:0}.nova-donation-title{font-size:22px!important}}
	</style>';
	echo '<main class="nova-donation-page"><header class="nova-donation-heading"><div><small>NOVAHD SUPPORT</small><h1 class="nova-donation-title">'.htmlspecialchars($lang_donate['text_donate']).'</h1></div><div class="nova-donation-heading-mark">N</div></header>';
	if (isset($_GET['saved']) && $_GET['saved'] === '1' && $canEditDonation) {
		echo '<div class="nova-donation-saved">捐赠信息已保存并立即生效。</div>';
	}
	if (isset($_GET['visibility']) && $_GET['visibility'] === '1' && $canEditDonation) {
		echo '<div class="nova-donation-saved">捐赠功能已' . ($donationPublicEnabled ? '开启，当前所有用户可见。' : '关闭，当前仅主管可见。') . '</div>';
	}
	print("<table width=100% class=\"nova-donation-table\">");
	print("<tr><td colspan=2 class=\"text nova-donation-intro\" align=left>".$lang_donate['text_donation_note']."</td></tr>");
	if ($custom) {
	    echo sprintf('<tr><td class="text nova-donation-custom" align="left" colspan="2">%s</td></tr>', nova_donation_render_details($custom));
    }
	print("<tr>");
	if ($showpaypal){
?>
<td class=text align=left valign=top <?php echo $tdattr?>>
<b><?php echo $lang_donate['text_donate_with_paypal']?></b><br /><br />
<?php echo $lang_donate['text_donate_paypal_note']?>
 <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
  <input type="hidden" name="cmd" value="_xclick">
  <input type="hidden" name="business" value="<?php echo $paypal;?>">
  <input type="hidden" name="item_name" value="Donation to <?php echo $SITENAME;?>">
  <p align="center">
<br />
  <?php echo $lang_donate['text_select_donation_amount']?>
<br />
   <select name="amount">
<option value="" selected><?php echo $lang_donate['select_choose_donation_amount']?></option>
<?php
$allowedDonationUsdAmounts = array(0, 1, 5, 10, 15, 20, 30, 40, 50, 60, 100, 300);
//$allowedDonationUsdAmounts = array(32, 64, 320);
foreach ($allowedDonationUsdAmounts as $amount) {
	if ($amount == 0) {
		echo '<option value="">'.$lang_donate['select_other_donation_amount'].'</option>';
	} else {
		$amount = number_format($amount, 2);
		echo '<option value='.$amount.'>'.$lang_donate['text_usd_mark'].$amount.$lang_donate['text_donation'].'</option>';
	}
}
?>
</select>
    <input type="hidden" name="image_url" value="">
    <input type="hidden" name="shipping" value="0">
    <input type="hidden" name="currency_code" value="USD">
    <input type="hidden" name="return" value="<?php echo  get_protocol_prefix() . $BASEURL;?>/donate.php?do=thanks">
<input type="hidden" name="cancel_return" value="<?php echo get_protocol_prefix() . $BASEURL;?>/donate.php">
<br />
</p>
<p align="center">
<input type="image" src="pic/paypalbutton.gif" border="0" name="I1" alt="Make payments with PayPal">
<br /><br /></p>
</form></td>
<?php
}
if ($showalipay){
?>
<td class=text align=left valign=top <?php echo $tdattr?>>
<b><?php echo $lang_donate['text_donate_with_alipay']?></b>
<p class="nova-donation-note">请使用支付宝扫描下方官方收款码，付款时备注你的站内用户名。</p>
<?php if ($showAlipayQr) { ?>
<div class="nova-donation-qr"><img src="<?php echo $alipayQrDisplay?>" alt="支付宝收款二维码" /></div>
<?php } else { ?>
<div class="nova-donation-pending">支付宝收款二维码尚未配置，请先联系财务获取收款码。</div>
<?php } ?>
<p class="nova-donation-note">完成转账后，请通过下方按钮发送付款金额、时间和站内用户名，由财务确认捐赠。</p>
</td>
<?php
}
print("</tr>");
print("<tr><td class=text colspan=2 align=left>".$lang_donate['text_after_donation_note_one']
."<a href=\"sendmessage.php?receiver=".$ACCOUNTANTID."\"><font class=\"striking\"><b>".$lang_donate['text_send_us']."</b></font></a>".$lang_donate['text_after_donation_note_two']."</td></tr>");
print("</table>");
print('<div class="nova-donation-actions"><a class="nova-donation-contact" href="sendmessage.php?receiver='.$ACCOUNTANTID.'">联系财务确认捐赠</a></div>');
	if ($canEditDonation) {
		$detailsValue = htmlspecialchars($custom, ENT_QUOTES, 'UTF-8');
		$qrUrlValue = htmlspecialchars($alipayQrUrl, ENT_QUOTES, 'UTF-8');
		$tokenValue = htmlspecialchars($donationEditToken, ENT_QUOTES, 'UTF-8');
		echo '<details class="nova-donation-manage"'.(isset($_GET['edit']) ? ' open' : '').'>';
		echo '<summary>编辑捐赠信息</summary>';
		echo '<form class="nova-donation-visibility ' . ($donationPublicEnabled ? 'is-on' : 'is-off') . '" method="post" action="donate.php">';
		echo '<input type="hidden" name="donation_action" value="toggle_visibility" />';
		echo '<input type="hidden" name="token" value="'.$tokenValue.'" />';
		echo '<input type="hidden" name="donation_public_enabled" value="' . ($donationPublicEnabled ? 'no' : 'yes') . '" />';
		echo '<div class="nova-donation-visibility-copy"><strong>捐赠功能：' . ($donationPublicEnabled ? '已开启' : '已关闭') . '</strong><span>' . ($donationPublicEnabled ? '所有用户均可见。' : '普通用户已隐藏，主管仍可查看和编辑。') . '</span></div>';
		echo '<button type="submit" class="btn">' . ($donationPublicEnabled ? '关闭捐赠' : '开启捐赠') . '</button></form>';
		echo '<form class="nova-donation-editor" method="post" action="donate.php">';
		echo '<input type="hidden" name="donation_action" value="save" />';
		echo '<input type="hidden" name="token" value="'.$tokenValue.'" />';
		echo '<div class="nova-donation-field"><label for="donation-alipay-qr">支付宝二维码图片地址</label><input id="donation-alipay-qr" type="url" name="donation_alipay_qr_url" value="'.$qrUrlValue.'" placeholder="https://..." /><span class="nova-donation-help">仅支持 HTTPS 图片地址；留空时前台显示尚未配置提示。</span></div>';
		echo '<div class="nova-donation-field"><label for="donation-details">捐赠详情</label><textarea id="donation-details" name="donation_details" maxlength="20000" placeholder="填写捐赠说明、奖励规则、确认流程等内容">'.$detailsValue.'</textarea><span class="nova-donation-help">支持 # 标题、## 分区、**加粗**、套餐与特惠价自动排版；使用 --- 分隔不同语言。</span></div>';
		echo '<div class="nova-donation-save"><button type="submit" class="btn">保存捐赠信息</button></div>';
		echo '</form></details>';
	}
	echo '</main><script>(function(){var tabs=document.querySelectorAll("[data-language-tab]"),panels=document.querySelectorAll("[data-language-panel]");tabs.forEach(function(tab){tab.addEventListener("click",function(){var target=tab.getAttribute("data-language-tab");tabs.forEach(function(item){var active=item===tab;item.classList.toggle("is-active",active);item.setAttribute("aria-selected",active?"true":"false")});panels.forEach(function(panel){panel.classList.toggle("is-active",panel.getAttribute("data-language-panel")===target)})})})})();</script>';
	end_main_frame();
stdfoot();
}
?>
