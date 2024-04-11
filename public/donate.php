<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
if ($enabledonation != 'yes')
	stderr($lang_donate['std_sorry'], $lang_donate['std_do_not_accept_donation']);

$do = $_GET['do'] ?? '';

if ($do == 'thanks') {
	stderr($lang_donate['std_success'], $lang_donate['std_donation_success_note_one']."<a href=\"sendmessage.php?receiver=".$ACCOUNTANTID."\"><b>".$lang_donate['std_here']."</b></a>".$lang_donate['std_donation_success_note_two'], false);
}
else
{
    /*
    $custom = trim(\App\Models\Setting::getByName('misc.donation_custom'));
	$paypal = safe_email($PAYPALACCOUNT);
	if ($paypal && check_email($paypal))
		$showpaypal = true;
	else
		$showpaypal = false;
	$alipay = safe_email($ALIPAYACCOUNT);
	if ($alipay && check_email($alipay))
		$showalipay = true;
	else
		$showalipay = false;

	if ($showpaypal && $showalipay)
		$tdattr = "width=\"50%\"";
	elseif ($showpaypal || $showalipay)
		$tdattr = "colspan=\"2\" width=\"100%\"";

	if (!$showpaypal && !$showalipay && !$custom) {
        stderr($lang_donate['std_error'], $lang_donate['std_no_donation_account_available'], false);
    }
    */
	stdhead($lang_donate['head_donation']);
	begin_main_frame();
	/*print("<h2>".$lang_donate['text_donate']."</h2>");
	print("<table width=100%>");
	print("<tr><td colspan=2 class=text align=left>".$lang_donate['text_donation_note']."</td></tr>");
	if ($custom) {
	    echo sprintf('<tr><td class="text" align="left" colspan="2">%s</td></tr>', format_comment($custom));
    }
	print("<tr>");
	if ($showpaypal){*/
?>
<!--
<td class=text align=left valign=top <?php /*echo $tdattr*/?>>
<b><?php /*echo $lang_donate['text_donate_with_paypal']*/?></b><br /><br />
<?php /*echo $lang_donate['text_donate_paypal_note']*/?>
 <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
  <input type="hidden" name="cmd" value="_xclick">
  <input type="hidden" name="business" value="<?php /*echo $paypal;*/?>">
  <input type="hidden" name="item_name" value="Donation to <?php /*echo $SITENAME;*/?>">
  <p align="center">
<br />
  <?php /*echo $lang_donate['text_select_donation_amount']*/?>
<br />
   <select name="amount">
<option value="" selected><?php /*echo $lang_donate['select_choose_donation_amount']*/?></option>
-->

<h2>感谢您考虑捐赠！</h2>
<table width='100%'>
<tr>
<td class='text' >
<h4>公益捐赠说明：</h4>
<p>我们致力于建立一个非盈利的资源分享网站，我们同时希望能够通过这个网站连接有心人士与需要帮助的个体。</p>
<p>通过您的捐赠，无论大小，都能在国内外正规公益组织中发挥重要作用，帮助那些处于困境中的人们。</p>
<p style='font-weight:600;color:red;'>青蛙不接受任何形式对本站点的捐赠。</p>

<h4>如何捐赠：</h4>
<p>选择您想支持的公益组织，并通过其官方渠道以个人名义进行捐赠，这既简单又快捷，确保您的善举不会遇到任何障碍。您也可以选择其他公共捐赠机构，但请确保能够在该机构的官方网站上查询到捐赠信息，以便我们核实您的捐赠记录。</p>
<p>每一份贡献都孕育着无限的可能性。您的每一份支持，都能为那些需要帮助的人带来希望和变化。让我们携手并肩，用实际行动传递爱心和温暖。</p>
<p>请注意，各慈善机构接受的捐赠方式可能会有所不同。我们恳请大家不要伪造捐赠证明（如通过PS等软件修改图片）。这是一项公益活动，我们希望大家能够真诚参与，不要利用伪造的捐赠记录欺骗管理团队。</p>

<h4>捐赠等级：</p>
<table style="border-collapse: collapse;margin:0 auto;padding: 0;box-shadow: 0 0 10px #CCC;border-radius: 6px;">
<thead style="background-color: rgba(198, 227, 198, .6);text-align: center;font-weight: 600;">

<td style="border: none;padding:10px 20px;border-radius: 6px 0 0 0;">金额</td>
<td style="border: none;padding:10px 20px;border-radius:0;">VIP时长</td>
<td style="border: none;padding:10px 20px;border-radius:0;">蝌蚪赠送</td>
<td style="border: none;padding:10px 20px;border-radius:0;">上传赠送</td>
<td style="border: none;padding:10px 20px;border-radius:0;">邀请赠送</td>
<td style="border: none;padding:10px 20px;border-radius: 0 6px 0 0;">捐赠徽章</td>
</tr>
</thead>
<tbody>
<tr style="background-color: #EEE;">
<td style="border: none;padding:10px 20px;border-radius:0;">一次性30</td>
<td style="border: none;padding:10px 20px;border-radius:0;">1月VIP/免考入站</td>
<td style="border: none;padding:10px 20px;border-radius:0;">0</td>
<td style="border: none;padding:10px 20px;border-radius:0;">0</td>
<td style="border: none;padding:10px 20px;border-radius:0;">0</td>
<td style="border: none;padding:10px 20px;border-radius:0;">特色捐赠徽章</td>
</tr>
<tr style="background-color: #fff;">
<td style="border: none;padding:10px 20px;border-radius:0;">一次性150</td>
<td style="border: none;padding:10px 20px;border-radius:0;">6月VIP </td>
<td style="border: none;padding:10px 20px;border-radius:0;">80000</td>
<td style="border: none;padding:10px 20px;border-radius:0;">0.5TiB</td>
<td style="border: none;padding:10px 20px;border-radius:0;">2</td>
<td style="border: none;padding:10px 20px;border-radius:0;">特色捐赠徽章</td>
</tr>
<tr style="background-color: #fff;">
<td style="border: none;padding:10px 20px;border-radius:0;">一次性180</td>
<td style="border: none;padding:10px 20px;border-radius:0;">永久黄星</td>
<td style="border: none;padding:10px 20px;border-radius:0;">100000</td>
<td style="border: none;padding:10px 20px;border-radius:0;">1TiB </td>
<td style="border: none;padding:10px 20px;border-radius:0;">3</td>
<td style="border: none;padding:10px 20px;border-radius:0;">特色捐赠徽章</td>
</tr>
<tr style="background-color: #EEE;">
<td style="border: none;padding:10px 20px;border-radius:0;">一次性1000</td>
<td style="border: none;padding:10px 20px;border-radius:0;">永V+永黄</td>
<td style="border: none;padding:10px 20px;border-radius:0;">800000</td>
<td style="border: none;padding:10px 20px;border-radius:0;">5TiB</td>
<td style="border: none;padding:10px 20px;border-radius:0;">10</td>
<td style="border: none;padding:10px 20px;border-radius:0;">定制捐赠徽章</td>
</tr>
<tr style="background-color: #fff;">
<td style="border: none;padding:10px 20px;border-radius: 0 0 0 6px;">自由发挥</td>
<td style="border: none;padding:10px 20px;border-radius:0;">站内视情况而定</td>
<td style="border: none;padding:10px 20px;border-radius:0;">站内视情况而定</td>
<td style="border: none;padding:10px 20px;border-radius:0;">站内视情况而定</td>
<td style="border: none;padding:10px 20px;border-radius:0;">站内视情况而定</td>
<td style="border: none;padding:10px 20px;border-radius: 0 0 6px 0;">特色捐赠徽章</td>
</tr>
</tbody>
</table>
<h4>说明：</h4>
	<p>黄星期间：免考核、双倍蝌蚪。</p>
    <p>VIP期间：免考核、不计分享率。</p>
    <p><span style='color:red;font-weight:900'>捐献仅计算单次金额，不计算累计。</span></p>
<h4>捐赠流程：</h4>
<p>登录您选择的捐赠网站，<span style='color:red;font-weight:900'>捐赠人请填写qw+站内用户名</span>，不匿名捐赠留存好自己的捐赠凭证。</p>
<p>捐赠完成后PM管理组，内容包含捐赠的网站、捐赠时间、捐赠金额，捐赠凭证，站内UID+用户名，在PM管理组前请确认在在网站捐赠可查询此次捐赠；</p>
<p>针对无号人员请发送，捐赠的网站、捐赠时间、捐赠金额，捐赠凭证，预注册邮箱及预注册ID，到邮箱<a href=mailto:admin@qingwa.pro>admin@qingwa.pro</a></p>

<h4>捐赠路径：</h4>
<p style="margin:10px;background-color:#FFF; display:inline-block;padding:10px;"><a href="https://www.chinacharityfederation.org/"><img src="zhcszh.png"></a></p>
<p></p>
<p style="margin:10px;background-color:#FFF; display:inline-block;padding:10px;"><a href="https://www.hhax.org/"><img src="https://www.hhax.org/statics/images/logo.png"></a></p>

</td>
</tr>
</table>

<?php
/*
$allowedDonationUsdAmounts = array(0, 1, 5, 10, 15, 20, 30, 40, 50, 60, 100, 300);
//$allowedDonationUsdAmounts = array(32, 64, 320);
foreach ($allowedDonationUsdAmounts as $amount) {
	if ($amount == 0) {
		echo '<option value="">'.$lang_donate['select_other_donation_amount'].'</option>';
	} else {
		$amount = number_format($amount, 2);
		echo '<option value='.$amount.'>'.$lang_donate['text_usd_mark'].$amount.$lang_donate['text_donation'].'</option>';
	}
}*/
?>
<!--
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
/*
}
if ($showalipay){*/
?>
<td class=text align=left valign=top <?php /*echo $tdattr*/?>>
<b><?php /*echo $lang_donate['text_donate_with_alipay']*/?></b><br /><br />
<form action="https://www.alipay.com/trade/fast_pay.htm" method="get">
<?php /*echo $lang_donate['text_donate_alipay_note_one']."<b>".$alipay."</b>".$lang_donate['text_donate_alipay_note_two']*/?>
<br /><br /><br /><br /><br />
<p align="center">
<input type="image" src="pic/alipaybutton.gif" border="0" name="I2" alt="Make payments with Alipay" />
<br /><br /></p>
</form></td>
-->
<?php
/*
}
print("</tr>");
print("<tr><td class=text colspan=2 align=left>".$lang_donate['text_after_donation_note_one']
."<a href=\"sendmessage.php?receiver=".$ACCOUNTANTID."\"><font class=\"striking\"><b>".$lang_donate['text_send_us']."</b></font></a>".$lang_donate['text_after_donation_note_two']."</td></tr>");
print("</table>");*/

end_main_frame();
stdfoot();
}
?>
