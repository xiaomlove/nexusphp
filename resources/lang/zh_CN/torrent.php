<?php

return [
    'pos_state_normal' => '普通',
    'pos_state_sticky' => '一级置顶',
    'pos_state_r_sticky' => '二级置顶',

    'index' => [
        'page_title' => '种子列表',
    ],
    'show' => [
        'page_title' => '种子详情',
        'basic_category' => '类型',
        'basic_audio_codec' => '音频编码',
        'basic_codec' => '视频编码',
        'basic_media' => '媒介',
        'basic_source' => '来源',
        'basic_standard' => '分辨率',
        'basic_team' => '制作组',
        'size' => '大小',
        'comments_label' => '评论',
        'times_completed_label' => '完成',
        'peers_count_label' => '同伴',
        'thank_users_count_label' => '谢谢',
        'numfiles_label' => '文件',
        'bookmark_yes_label' => '已收藏',
        'bookmark_no_label' => '收藏',
        'reward_logs_label' => '赠魔',
        'reward_yes_label' => '已赠魔',
        'reward_no_label' => '赠魔',
        'download_label' => '下载',
        'thanks_yes_label' => '已谢谢',
        'thanks_no_label' => '谢谢',
    ],
    'pick_info' => [
        'normal' => '普通',
        'hot' => '热门',
        'classic' => '经典',
        'recommended' => '推荐',
    ],
    'claim_already' => '此种子已经认领',
    'no_snatch' => '没有下载过此种子',
    'can_no_be_claimed_yet' => '还不能被认领',
    'claim_number_reach_user_maximum' => '认领达到人数上限',
    'claim_number_reach_torrent_maximum' => '认领达到种子数上限',
    'claim_disabled' => '认领未启用',
    'operation_log' => [
        \App\Models\TorrentOperationLog::ACTION_TYPE_APPROVAL_DENY => [
            'type_text' => '禁止',
            'notify_subject' => '种子被禁止',
            'notify_msg' => '你的种子：[url=:detail_url]:torrent_name[/url] 被 :operator 禁止，原因：:reason',
        ],
        \App\Models\TorrentOperationLog::ACTION_TYPE_APPROVAL_ALLOW => [
            'type_text' => '取消禁止',
            'notify_subject' => '种子取消禁止',
            'notify_msg' => '你的种子：[url=:detail_url]:torrent_name[/url] 被 :operator 取消禁止',
        ],
        \App\Models\TorrentOperationLog::ACTION_TYPE_APPROVAL_NONE => [
            'type_text' => '取消禁止',
            'notify_subject' => '种子取消禁止',
            'notify_msg' => '你的种子：[url=:detail_url]:torrent_name[/url] 被 :operator 取消禁止',
        ]
    ],
    'owner_update_torrent_subject' => '被禁种子已更新',
    'owner_update_torrent_msg' => '种子：[url=:detail_url]:torrent_name[/url] 已被作者更新，可以检查是否符合要求并取消禁止',
    'approval' => [
        'modal_title' => '种子审核',
        'status_label' => '审核状态',
        'comment_label' => '备注(可选)',
        'status_text' => [
            \App\Models\Torrent::APPROVAL_STATUS_NONE => '未审',
            \App\Models\Torrent::APPROVAL_STATUS_ALLOW => '通过',
            \App\Models\Torrent::APPROVAL_STATUS_DENY => '拒绝',
        ],
        'deny_comment_show' => '审核不通过，原因：:reason',
    ],
];
