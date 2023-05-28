<?php

$lang_recover = array
(
	'std_recover_failed' => "找回密码失败！(请看下面)",
	'std_missing_email_address' => "你必须输入邮箱地址！",
	'std_invalid_email_address' => "无效的邮箱地址！",
	'std_email_not_in_database' => "数据库中不存在该邮箱地址。",
	'std_error' => "错误",
	'std_database_error' => "数据库错误。请将该错误告诉管理员。",
	'std_unable_updating_user_data' => "无法更新用户数据。请将该错误向管理员报告。",
	'text_recover_user' => "找回用户名或密码",
	'text_use_form_below' => "使用以下表格重置密码，更新后的帐户信息会发送到你的邮箱。",
	'text_reply_to_confirmation_email' => "(请按邮件指示执行)",
	'text_note' => "注意：连续",
	'text_ban_ip' => "次错误尝试会导致你的IP地址被禁用！",
	'row_registered_email' => "注册邮箱：",
	'submit_recover_it' => "确定",
	'text_you_have' => "你还有",
	'text_remaining_tries' => "次尝试机会。",

	'mail_this_link' => "这个链接",
	'mail_here' => "这里",
	
	'mail_title' => " 网站密码重置验证",
	'mail_one' => "你好,<br /><br />你请求重置你在".$SITENAME."网站账户的密码。<br />该账户的邮箱地址为 ",
	'mail_two' => " 。<br /><br />发送请求的IP地址为 ",
	'mail_three' => ".<br /><br />如果你没有发过该请求，请忽视本邮件。请勿回复本邮件。<br /><br />如果你的确发过该请求，请点击这个链接来确认: ",
	'mail_four' => "<br />确认后，你的密码将被重置并通过另一封邮件发送给你。<br /><br />------<br />".$SITENAME." 网站",
	
	'mail_two_title' => " 网站账户信息",
	'mail_two_one' => "你好，<br /><br />依你的请求，我们给你的账户生成了新的密码。<br /><br />以下是你的账户重置后的信息：<br /><br />用户名：",
	'mail_two_two' => "<br />密码：",
	'mail_two_three' => "<br /><br />你可以从这里登录： ",
	'mail_two_four' => "<br /><br />登录后你可以在控制面板-安全设定中修改密码。<br />------<br />".$SITENAME." 网站",
	'text_select_lang' => "Select Site Language: ",
	'std_user_account_unconfirmed' => "该账户还未通过验证。如果你没有收到验证邮件，试试<a href='confirm_resend.php'><b>重新发送验证邮件</b></a>。",
);
?>
