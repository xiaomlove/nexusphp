<?php

$lang_recover = array
(
	'std_recover_failed' => "找回密碼失敗！(請看下麵)",
	'std_missing_email_address' => "你必須輸入郵箱地址！",
	'std_invalid_email_address' => "無效的郵箱地址！",
	'std_email_not_in_database' => "數據庫中不存在該郵箱地址。",
	'std_error' => "錯誤",
	'std_database_error' => "數據庫錯誤。請將該錯誤告訴管理員。",
	'std_unable_updating_user_data' => "無法更新用戶資料。請將該錯誤向管理員報告。",
	'text_recover_user' => "找回用戶名或密碼",
	'text_use_form_below' => "使用以下表格重置密碼，更新後的帳戶資訊會發送到你的郵箱。",
	'text_reply_to_confirmation_email' => "(請按郵件指示執行)",
	'text_note' => "注意：連續",
	'text_ban_ip' => "次錯誤嘗試會導致你的IP地址被禁用！",
	'row_registered_email' => "註冊郵箱：",
	'submit_recover_it' => "確定",
	'text_you_have' => "你還有",
	'text_remaining_tries' => "次嘗試機會。",
	
	'mail_this_link' => "這個鏈接",
	'mail_here' => "這裏",
	
	'mail_title' => " 網站密碼重置驗證",
	'mail_one' => "你好,<br /><br />你請求重置你在".$SITENAME."網站賬戶的密碼。<br />該賬戶的郵箱地址為 ",
	'mail_two' => " 。<br /><br />發送請求的IP地址為 ",
	'mail_three' => ".<br /><br />如果你沒有發過該請求，請忽視本郵件。請勿回復本郵件。<br /><br />如果你的確發過該請求，請點擊這個鏈接來確認: ",
	'mail_four' => "<br />確認後，你的密碼將被重置並通過另一封郵件發送給你。<br /><br />------<br />".$SITENAME." 網站",
	
	'mail_two_title' => " 網站賬戶信息",
	'mail_two_one' => "你好，<br /><br />依你的請求，我們給你的賬戶生成了新的密碼。<br /><br />以下是你的賬戶重置後的信息：<br /><br />用戶名：",
	'mail_two_two' => "<br />密碼：",
	'mail_two_three' => "<br /><br />你可以從這裏登錄： ",
	'mail_two_four' => "<br /><br />登錄後你可以在控制面板-安全設定中修改密碼。<br />------<br />".$SITENAME." 網站",
	'text_select_lang' => "Select Site Language: ",
	'std_user_account_unconfirmed' => "該賬戶還未通過驗證。如果你沒有收到驗證郵件，試試<a href='confirm_resend.php'><b>重新發送驗證郵件</b></a>。",
);
?>
