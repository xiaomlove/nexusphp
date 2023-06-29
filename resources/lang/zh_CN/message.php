<?php

return [

    'index' => [
        'page_title' => '私信列表',
    ],
    'show' => [
        'page_title' => '私信详情',
    ],
    'field_value_change_message_body' => ':field 被管理员 :operator 从 :old 改为 :new。理由：:reason。',
    'field_value_change_message_subject' => ':field 改变',

    'download_disable' => [
        'subject' => '下载权限取消',
        'body' => '你的下载权限被取消，可能的原因是过低的分享率或行为不当。By: :operator',
    ],
    'download_disable_upload_over_speed' => [
        'subject' => '下载权限取消',
        'body' => '你因上传速度过快下载权限被取消，若是盒子用户请备案。',
    ],
    'download_enable' => [
        'subject' => '下载权限恢复',
        'body' => '你的下载权限恢复，你现在可以下载种子。By: :operator',
    ],
    'temporary_invite_change' => [
        'subject' => '临时邀请:change_type',
        'body' => '你的临时邀请被管理员 :operator :change_type :count 个，理由：:reason。',
    ],
    'receive_medal' => [
        'subject' => '收到赠送勋章',
        'body' => '用户 :username 花费魔力 :cost_bonus 购买了勋章[:medal_name]并赠送与你。此勋章价值 :price，手续费 :gift_fee_total(系数：:gift_fee_factor)，你将拥有此勋章有效期至: :expire_at，勋章的魔力加成系数为: :bonus_addition_factor。',
    ],
    'login_notify' => [
        'subject' => ':site_name 异地登录提醒',
        'body' => <<<BODY
你于: :this_login_time 进行了登录操作。IP：:this_ip，位置：:this_location。<br/>
上次登录时间：:last_login_time，IP：:last_ip，位置：:last_location。<br/>
若不是你本人操作，账号密码可能已经泄露，请及时修改！
BODY,
    ],
    'buy_torrent_success' => [
        'subject' => '成功购买种子提醒',
        'body' => '你花费 :bonus 魔力成功购买了种子：[url=:url]:torrent_name[/url]',
    ],
];
