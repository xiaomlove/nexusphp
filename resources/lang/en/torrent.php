<?php

return [
    'pos_state_normal' => 'Normal',
    'pos_state_sticky' => 'Sticky first',
    'pos_state_r_sticky' => 'Sticky second',

    'index' => [
        'page_title' => 'Torrent list',
    ],
    'show' => [
        'page_title' => 'Torrent detail',
        'basic_category' => 'Category',
        'basic_audio_codec' => 'Audio codec',
        'basic_codec' => 'Video codec',
        'basic_media' => 'Media',
        'basic_source' => 'Source',
        'basic_standard' => 'Standard',
        'basic_team' => 'Team',
        'size' => 'Size',
        'comments_label' => 'Comments',
        'times_completed_label' => 'Snatched',
        'peers_count_label' => 'Peers',
        'thank_users_count_label' => 'Thanks',
        'numfiles_label' => 'Files',
        'bookmark_yes_label' => 'Bookmarked',
        'bookmark_no_label' => 'Add to bookmark',
        'reward_logs_label' => 'Reward',
        'reward_yes_label' => 'Rewarded',
        'reward_no_label' => 'Reward',
        'download_label' => 'Download',
        'thanks_yes_label' => 'Thanked',
        'thanks_no_label' => 'Thank',
    ],
    'pick_info' => [
        'normal' => 'Normal',
        'hot' => 'Hot',
        'classic' => 'Classic',
        'recommended' => 'Recommend',
    ],
    'claim_already' => 'Claimed already',
    'no_snatch' => 'Never download this torrent yet',
    'can_no_be_claimed_yet' => 'Can not be claimed yet',
    'claim_number_reach_user_maximum' => 'The maximum number of user is reached',
    'claim_number_reach_torrent_maximum' => 'The maximum number of torrent is reached',
    'claim_disabled' => 'Claim is disabled',
    'operation_log' => [
        \App\Models\TorrentOperationLog::ACTION_TYPE_APPROVAL_DENY => [
            'type_text' => 'Banned',
            'notify_subject' => 'Torrent was banned',
            'notify_msg' => 'Your torrent：[url=:detail_url]:torrent_name[/url] was banned by :operator, Reason: :reason',
        ],
        \App\Models\TorrentOperationLog::ACTION_TYPE_APPROVAL_ALLOW => [
            'type_text' => 'Cancel banned',
            'notify_subject' => 'Torrent was unbanned',
            'notify_msg' => 'Your torrent: [url=:detail_url]:torrent_name[/url] unbanned by :operator',
        ],
        \App\Models\TorrentOperationLog::ACTION_TYPE_APPROVAL_NONE => [
            'type_text' => 'Cancel banned',
            'notify_subject' => 'Torrent was unbanned',
            'notify_msg' => 'Your torrent: [url=:detail_url]:torrent_name[/url] unbanned by :operator',
        ]
    ],
    'owner_update_torrent_subject' => 'Banned torrent have been updated',
    'owner_update_torrent_msg' => 'Torrent：[url=:detail_url]:torrent_name[/url] has been updated by the owner, you can check if it meets the requirements and cancel the ban',
    'approval' => [
        'modal_title' => 'Torrent approval',
        'status_label' => 'Approval status',
        'comment_label' => 'Comment(optional)',
        'status_text' => [
            \App\Models\Torrent::APPROVAL_STATUS_NONE => 'Not reviewed',
            \App\Models\Torrent::APPROVAL_STATUS_ALLOW => 'Approved',
            \App\Models\Torrent::APPROVAL_STATUS_DENY => 'Not approved',
        ],
        'deny_comment_show' => 'Denied, reason: :reason',
    ],
];
