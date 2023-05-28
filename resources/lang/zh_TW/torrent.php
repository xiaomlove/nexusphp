<?php

return [
    'pos_state_normal' => '普通',
    'pos_state_sticky' => '一級置頂',
    'pos_state_r_sticky' => '二級置頂',

    'index' => [
        'page_title' => '種子列表',
    ],
    'show' => [
        'page_title' => '種子詳情',
        'basic_category' => '類型',
        'basic_audio_codec' => '音頻編碼',
        'basic_codec' => '視頻編碼',
        'basic_media' => '媒介',
        'basic_source' => '來源',
        'basic_standard' => '分辨率',
        'basic_team' => '製作組',
        'size' => '大小',
        'comments_label' => '評論',
        'times_completed_label' => '完成',
        'peers_count_label' => '同伴',
        'thank_users_count_label' => '謝謝',
        'numfiles_label' => '文件',
        'bookmark_yes_label' => '已收藏',
        'bookmark_no_label' => '收藏',
        'reward_logs_label' => '贈魔',
        'reward_yes_label' => '已贈魔',
        'reward_no_label' => '贈魔',
        'download_label' => '下載',
        'thanks_yes_label' => '已謝謝',
        'thanks_no_label' => '謝謝',
    ],
    'pick_info' => [
        'normal' => '普通',
        'hot' => '熱門',
        'classic' => '經典',
        'recommended' => '推薦',
    ],
    'claim_already' => '此種子已經認領',
    'no_snatch' => '沒有下載過此種子',
    'can_no_be_claimed_yet' => '還不能被認領',
    'claim_number_reach_user_maximum' => '認領達到人數上限',
    'claim_number_reach_torrent_maximum' => '認領達到種子數上限',
    'claim_disabled' => '認領未啟用',
    'operation_log' => [
        \App\Models\TorrentOperationLog::ACTION_TYPE_APPROVAL_DENY => [
            'type_text' => '審核拒絕',
            'notify_subject' => '種子審核拒絕',
            'notify_msg' => '妳的種子：[url=:detail_url]:torrent_name[/url] 被 :operator 審核拒絕，原因：:reason',
        ],
        \App\Models\TorrentOperationLog::ACTION_TYPE_APPROVAL_ALLOW => [
            'type_text' => '審核通過',
            'notify_subject' => '種子審核通過',
            'notify_msg' => '妳的種子：[url=:detail_url]:torrent_name[/url] 被 :operator 審核通過',
        ],
        \App\Models\TorrentOperationLog::ACTION_TYPE_APPROVAL_NONE => [
            'type_text' => '標記未審核',
            'notify_subject' => '種子標記未審核',
            'notify_msg' => '妳的種子：[url=:detail_url]:torrent_name[/url] 被 :operator 標記未審核',
        ],
        \App\Models\TorrentOperationLog::ACTION_TYPE_EDIT => [
            'type_text' => '編輯',
            'notify_subject' => '種子被編輯',
            'notify_msg' => '你的種子：[url=:detail_url]:torrent_name[/url] 被 :operator 編輯',
        ],
        \App\Models\TorrentOperationLog::ACTION_TYPE_DELETE => [
            'type_text' => '刪除',
            'notify_subject' => '種子被刪除',
            'notify_msg' => '你的種子：:torrent_name 被 :operator 刪除',
        ]
    ],
    'owner_update_torrent_subject' => '審核拒絕種子已更新',
    'owner_update_torrent_msg' => '種子：[url=:detail_url]:torrent_name[/url] 已被作者更新，可以檢查是否符合要求併審核通過',
    'approval' => [
        'modal_title' => '種子審核',
        'status_label' => '審核狀態',
        'comment_label' => '備註(可選)',
        'status_text' => [
            \App\Models\Torrent::APPROVAL_STATUS_NONE => '未審',
            \App\Models\Torrent::APPROVAL_STATUS_ALLOW => '通過',
            \App\Models\Torrent::APPROVAL_STATUS_DENY => '拒絕',
        ],
        'deny_comment_show' => '審核不通過，原因：:reason',
        'logs_label' => '審核記錄'
    ],
    'show_hide_media_info' => '顯示/隱藏原始 MediaInfo',
    'promotion_time_types' => [
        \App\Models\Torrent::PROMOTION_TIME_TYPE_GLOBAL => '全局',
        \App\Models\Torrent::PROMOTION_TIME_TYPE_PERMANENT => '永久',
        \App\Models\Torrent::PROMOTION_TIME_TYPE_DEADLINE => '直到',
    ],
    'paid_torrent' => '收費種子',
];
