<?php

return [
    'business_types' => [
        \App\Models\BonusLogs::BUSINESS_TYPE_CANCEL_HIT_AND_RUN => '消除 H&R',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_MEDAL => '购买勋章',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_ATTENDANCE_CARD => '购买补签卡',
        \App\Models\BonusLogs::BUSINESS_TYPE_STICKY_PROMOTION => '置顶促销',
        \App\Models\BonusLogs::BUSINESS_TYPE_POST_REWARD => '帖子奖励',
        \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_UPLOAD => '兑换上传量',
        \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_INVITE => '购买邀请',
        \App\Models\BonusLogs::BUSINESS_TYPE_CUSTOM_TITLE => '自定义头衔',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_VIP => '购买 VIP',
        \App\Models\BonusLogs::BUSINESS_TYPE_GIFT_TO_SOMEONE => '捐赠给某人',
        \App\Models\BonusLogs::BUSINESS_TYPE_NO_AD => '消除广告',
        \App\Models\BonusLogs::BUSINESS_TYPE_GIFT_TO_LOW_SHARE_RATIO => '捐赠给低分享率者',
        \App\Models\BonusLogs::BUSINESS_TYPE_LUCKY_DRAW => '幸运大转盘',
        \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_DOWNLOAD => '兑换下载量',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_TEMPORARY_INVITE => '购买临时邀请',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_RAINBOW_ID => '购买彩虹 ID',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_CHANGE_USERNAME_CARD => '购买改名卡',
        \App\Models\BonusLogs::BUSINESS_TYPE_GIFT_MEDAL => '赠送勋章',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_TORRENT => '购买种子',

        \App\Models\BonusLogs::BUSINESS_TYPE_ROLE_WORK_SALARY => '工作组工资',
        \App\Models\BonusLogs::BUSINESS_TYPE_TORRENT_BE_DOWNLOADED => '种子被下载',
    ],
    'fields' => [
        'business_type' => '业务类型',
        'old_total_value' => '交易前值',
        'value' => '交易值',
        'new_total_value' => '交易后值',
    ],
];
