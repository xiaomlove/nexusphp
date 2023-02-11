<?php

return [
    'business_types' => [
        \App\Models\BonusLogs::BUSINESS_TYPE_CANCEL_HIT_AND_RUN => '消除 H&R',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_MEDAL => '購買勛章',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_ATTENDANCE_CARD => '購買補簽卡',
        \App\Models\BonusLogs::BUSINESS_TYPE_STICKY_PROMOTION => '置頂促銷',
        \App\Models\BonusLogs::BUSINESS_TYPE_POST_REWARD => '帖子獎勵',
        \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_UPLOAD => '兌換上傳量',
        \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_INVITE => '購買邀請',
        \App\Models\BonusLogs::BUSINESS_TYPE_CUSTOM_TITLE => '自定義頭銜',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_VIP => '購買 VIP',
        \App\Models\BonusLogs::BUSINESS_TYPE_GIFT_TO_SOMEONE => '捐贈給某人',
        \App\Models\BonusLogs::BUSINESS_TYPE_NO_AD => '消除廣告',
        \App\Models\BonusLogs::BUSINESS_TYPE_GIFT_TO_LOW_SHARE_RATIO => '捐贈給低分享率者',
        \App\Models\BonusLogs::BUSINESS_TYPE_LUCKY_DRAW => '幸運大轉盤',
        \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_DOWNLOAD => '兌換下載量',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_TEMPORARY_INVITE => '購買臨時邀請',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_RAINBOW_ID => '購買彩虹 ID',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_CHANGE_USERNAME_CARD => '購買改名卡',
        \App\Models\BonusLogs::BUSINESS_TYPE_GIFT_MEDAL => '贈送勛章',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_TORRENT => '購買種子',

        \App\Models\BonusLogs::BUSINESS_TYPE_ROLE_WORK_SALARY => '工作組工資',
        \App\Models\BonusLogs::BUSINESS_TYPE_TORRENT_BE_DOWNLOADED => '種子被下載',
    ],
    'fields' => [
        'business_type' => '業務類型',
        'old_total_value' => '交易前值',
        'value' => '交易值',
        'new_total_value' => '交易後值',
    ],
];
