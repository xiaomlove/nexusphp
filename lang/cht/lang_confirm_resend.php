<?php

$lang_confirm_resend = array
(
	'resend_confirmation_email_failed' => "重發確認郵件失敗",
	'std_fields_blank' => "有項目沒有填寫。",
	'std_invalid_email_address' => "郵箱地址無效！",
	'std_email_not_found' => "資料庫中沒有該郵箱地址。\n",
	'std_user_already_confirm' => "該郵箱的用戶已經通過驗證。\n",
	'std_passwords_unmatched' => "兩次輸入的密碼不一致！肯定拼錯了，請重試。",
	'std_password_too_short' => "對不起，密碼過短（至少6個字元）",
	'std_password_too_long' => "對不起，密碼過長（至多40個字元）",
	'std_password_equals_username' => "對不起，用戶名和密碼不能相同。",
	'std_error' => "錯誤",
	'std_database_error' => "數據庫錯誤！請將該情況報告給管理員。",
	'text_resend_confirmation_mail_note' => "<h1>重發確認郵件</h1><p>填寫以下表格使系統重發驗證郵件。</p><p>你必須在過去24小時內註冊且沒有通過驗證才能使用該功能，<br />否則你的帳號信息已被刪除，需要重新註冊。</p><p><b>注意：</b>連續".$maxloginattempts."次嘗試失敗將導致你的IP地址被禁用！</p>",
	'row_registered_email' => "註冊郵箱：",
	'row_new_password' => "新密碼：",
	'text_password_note' => "至少6個字元",
	'row_enter_password_again' => "再次輸入新密碼：",
	'submit_send_it' => "發送！",
	'text_you_have' => "你還有",
	'text_remaining_tries' => "次嘗試機會。",
	'mail_title' => "用戶註冊驗證（重發）",
	'mail_this_link' => "這個鏈接",
	'mail_here' => "這裏",
	'mail_one' => "你好 ",
	'mail_two' => ",<br /><br />你請求重新收取".$SITENAME."網站的註冊確認郵件，並指定此郵箱地址 ",
	'mail_three' => " 為你的聯系地址。<br /><br />如果你沒有發過該請求，請忽視本郵件。輸入你郵箱地址者的IP地址為 ",
	'mail_four' => ". 。請勿回復本郵件。<br /><br />如果你的確發過該請求，請點擊以下鏈接來通過驗證： ",
	'mail_four_1' => "<br /><br />如果以上鏈接打開出錯、不存在或已經過期, 嘗試在這裏重新發送確認郵件 ",
	'mail_five' => "在通過驗證後，你就可以使用新賬號了。<br /><br />如果你在24小時內沒有通過驗證，你的賬號將被刪除。<br />新人登錄".$SITENAME."後請務必先閱讀站點規則，提問前請參考常見問題。<br /><br />請註意：如果你並沒有在".$SITENAME."網站註冊，請舉報此郵件至".$REPORTMAIL."<br /><br />------<br /><br />".$SITENAME." 網站.",
	'text_select_lang' => "Select Site Language: ",
	'std_need_admin_verification' => "賬戶需要通過管理員手動驗証。"
);
?>
