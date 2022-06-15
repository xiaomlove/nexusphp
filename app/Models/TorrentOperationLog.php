<?php

namespace App\Models;

use Nexus\Database\NexusDB;

class TorrentOperationLog extends NexusModel
{
    protected $table = 'torrent_operation_logs';

    public $timestamps = true;

    protected $fillable = ['uid', 'torrent_id', 'action_type', 'comment'];

    const ACTION_TYPE_APPROVAL_NONE = 'approval_none';
    const ACTION_TYPE_APPROVAL_ALLOW = 'approval_allow';
    const ACTION_TYPE_APPROVAL_DENY = 'approval_deny';

    public static array $actionTypes = [
        self::ACTION_TYPE_APPROVAL_NONE => ['text' => 'Approval none'],
        self::ACTION_TYPE_APPROVAL_ALLOW => ['text' => 'Approval allow'],
        self::ACTION_TYPE_APPROVAL_DENY => ['text' => 'Approval deny'],
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'uid')->select(User::$commonFields);
    }

    public function torrent()
    {
        return $this->belongsTo(Torrent::class, 'torrent_id')->select(Torrent::$commentFields);
    }


    public static function add(array $params)
    {
        $log = self::query()->create($params);
        if (!in_array($params['action_type'], [self::ACTION_TYPE_APPROVAL_ALLOW, self::ACTION_TYPE_APPROVAL_DENY])) {
            do_log("actionType: {$params['action_type']}, do not notify");
            return $log;
        }
        self::notifyUser($log);
        return $log;
    }

    private static function notifyUser(self $torrentOperationLog)
    {
        $actionType = $torrentOperationLog->action_type;
        $receiver = $torrentOperationLog->torrent->user;
        $locale = $receiver->locale;
        $subject = nexus_trans("torrent.operation_log.$actionType.notify_subject", [], $locale);
        $msg = nexus_trans("torrent.operation_log.$actionType.notify_msg", [
            'torrent_name' => $torrentOperationLog->torrent->name,
            'detail_url' => sprintf('%s/details.php?id=%s', getSchemeAndHttpHost(), $torrentOperationLog->torrent_id),
            'operator' => $torrentOperationLog->user->username,
            'reason' => $torrentOperationLog->comment,
        ], $locale);
        $message = [
            'sender' => 0,
            'receiver' => $receiver->id,
            'subject' => $subject,
            'msg' => $msg,
            'added' => now(),
        ];
        Message::query()->insert($message);
        NexusDB::cache_del("user_{$receiver->id}_inbox_count");
    }
}
