<?php

return [
    'business_types' => [
        \App\Models\BonusLogs::BUSINESS_TYPE_CANCEL_HIT_AND_RUN => 'Cancel H&R',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_MEDAL => 'Buy medal',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_ATTENDANCE_CARD => 'Buy attendance card',
        \App\Models\BonusLogs::BUSINESS_TYPE_STICKY_PROMOTION => 'Sticky promotion',
        \App\Models\BonusLogs::BUSINESS_TYPE_POST_REWARD => 'Post reward',
        \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_UPLOAD => 'Exchange uploaded',
        \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_INVITE => 'Buy invite',
        \App\Models\BonusLogs::BUSINESS_TYPE_CUSTOM_TITLE => 'Custom title',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_VIP => 'Buy VIP',
        \App\Models\BonusLogs::BUSINESS_TYPE_GIFT_TO_SOMEONE => 'Gift to someone',
        \App\Models\BonusLogs::BUSINESS_TYPE_NO_AD => 'Cancel ad',
        \App\Models\BonusLogs::BUSINESS_TYPE_GIFT_TO_LOW_SHARE_RATIO => 'Gift to low share ratio',
        \App\Models\BonusLogs::BUSINESS_TYPE_LUCKY_DRAW => 'Lucky draw',
        \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_DOWNLOAD => 'Exchange downloaded',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_TEMPORARY_INVITE => 'Buy temporary invite',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_RAINBOW_ID => 'Buy rainbow ID',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_CHANGE_USERNAME_CARD => 'Buy change username card',
        \App\Models\BonusLogs::BUSINESS_TYPE_GIFT_MEDAL => 'Gift medal',
        \App\Models\BonusLogs::BUSINESS_TYPE_BUY_TORRENT => 'Buy torrent',

        \App\Models\BonusLogs::BUSINESS_TYPE_ROLE_WORK_SALARY => 'Role work salary',
        \App\Models\BonusLogs::BUSINESS_TYPE_TORRENT_BE_DOWNLOADED => 'Torrent be downloaded',
    ],
    'fields' => [
        'business_type' => 'Business type',
        'old_total_value' => 'Pre-trade value',
        'value' => 'Trade value',
        'new_total_value' => 'Post-trade value',
    ],
];
