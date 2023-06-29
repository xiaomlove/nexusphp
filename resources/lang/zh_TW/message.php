<?php

return [

    'index' => [
        'page_title' => '私信列表',
    ],
    'show' => [
        'page_title' => '私信詳情',
    ],
    'field_value_change_message_body' => ':field 被管理員 :operator 從 :old 改為 :new。理由：:reason。',
    'field_value_change_message_subject' => ':field 改變',
    'download_disable' => [
        'subject' => '下載權限取消',
        'body' => '你的下載權限被取消，可能的原因是過低的分享率或行為不當。By: :operator',
    ],
    'download_disable_upload_over_speed' => [
        'subject' => '下載權限取消',
        'body' => '你因上傳速度過快下載權限被取消，若是盒子用戶請備案。',
    ],
    'download_enable' => [
        'subject' => '下載權限恢復',
        'body' => '你的下載權限恢復，你現在可以下載種子。By: :operator',
    ],
    'temporary_invite_change' => [
        'subject' => '臨時邀請:change_type',
        'body' => '你的臨時邀請被管理員 :operator :change_type :count 個，理由：:reason。',
    ],
    'receive_medal' => [
        'subject' => '收到贈送勛章',
        'body' => '用戶 :username 花費魔力 :cost_bonus 購買了勛章[:medal_name]並贈送與你。此勛章價值 :price，手續費 :gift_fee_total(系數：:gift_fee_factor)，你將擁有此勛章有效期至: :expire_at，勛章的魔力加成系數為: :bonus_addition_factor。',
    ],
    'login_notify' => [
        'subject' => ':site_name 異地登錄提醒',
        'body' => <<<BODY
你於：:this_login_time 進行了登錄操作，IP：:this_ip，位置：:this_location。<br/>
上次登錄時間：:last_login_time，IP：:last_ip，位置：:last_location。<br/>
若不是你本人操作，賬號密碼可能已經泄露，請及時修改！
BODY,
    ],
    'buy_torrent_success' => [
        'subject' => '成功購買種子提醒',
        'body' => '你花費 :bonus 魔力成功購買了種子：[url=:url]:torrent_name[/url]',
    ],
];
