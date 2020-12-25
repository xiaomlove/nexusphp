<?php

$lang_confirm_resend = array
(
	'resend_confirmation_email_failed' => "重发确认邮件失败",
	'std_fields_blank' => "有项目没有填写。",
	'std_invalid_email_address' => "邮箱地址无效！",
	'std_email_not_found' => "数据库中没有该邮箱地址。\n",
	'std_user_already_confirm' => "该邮箱的用户已经通过验证。\n",
	'std_passwords_unmatched' => "两次输入的密码不一致！肯定拼错了，请重试。",
	'std_password_too_short' => "对不起，密码过短（至少6个字符）",
	'std_password_too_long' => "对不起，密码过长（至多40个字符）",
	'std_password_equals_username' => "对不起，用户名和密码不能相同。",
	'std_error' => "错误",
	'std_database_error' => "数据库错误！请将该情况报告给管理员。",
	'text_resend_confirmation_mail_note' => "<h1>重发确认邮件</h1><p>填写以下表格使系统重发验证邮件。</p><p>你必须在过去24小时内注册且没有通过验证才能使用该功能，<br />否则你的账号信息已被删除，需要重新注册。</p><p><b>注意：</b>连续".$maxloginattempts."次尝试失败将导致你的IP地址被禁用！</p>",
	'row_registered_email' => "注册邮箱：",
	'row_new_password' => "新密码：",
	'text_password_note' => "至少6个字符",
	'row_enter_password_again' => "再次输入新密码：",
	'submit_send_it' => "发送！",
	'text_you_have' => "你还有",
	'text_remaining_tries' => "次尝试机会。",
	'mail_title' => "用户注册验证（重发）",
	'mail_this_link' => "这个链接",
	'mail_here' => "这里",
	'mail_one' => "你好 ",
	'mail_two' => ",<br /><br />你请求重新收取".$SITENAME."网站的注册确认邮件，并指定此邮箱地址 ",
	'mail_three' => " 为你的联系地址。<br /><br />如果你没有发过该请求，请忽视本邮件。输入你邮箱地址者的IP地址为 ",
	'mail_four' => ". 。请勿回复本邮件。<br /><br />如果你的确发过该请求，请点击以下链接来通过验证： ",
	'mail_four_1' => "<br /><br />如果以上链接打开出错、不存在或已经过期, 尝试在这里重新发送确认邮件 ",
	'mail_five' => "在通过验证后，你就可以使用新账号了。<br /><br />如果你在24小时内没有通过验证，你的账号将被删除。<br />新人登录".$SITENAME."后请务必先阅读站点规则，提问前请参考常见问题。<br /><br />请注意：如果你并没有在".$SITENAME."网站注册，请举报此邮件至".$REPORTMAIL."<br /><br />------<br /><br />".$SITENAME." 网站.",
	'text_select_lang' => "Select Site Language: ",
	'std_need_admin_verification' => "账户需要通过管理员手动验证。"
);
?>
