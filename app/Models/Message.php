<?php

namespace App\Models;

use Nexus\Database\NexusDB;

class Message extends NexusModel
{
    protected $table = 'messages';

    protected $fillable = [
        'sender', 'receiver', 'added', 'subject', 'msg', 'unread', 'location', 'saved'
    ];

    protected $casts = [
        'added' => 'datetime',
    ];

    public function send_user()
    {
        return $this->belongsTo(User::class, 'sender')->withDefault(['id' => 0, 'username' => 'System']);
    }

    public function receive_user()
    {
        return $this->belongsTo(User::class, 'receiver');
    }

    public static function add(array $data): bool
    {
        NexusDB::cache_del('user_'.$data["receiver"].'_inbox_count');
        NexusDB::cache_del('user_'.$data["receiver"].'_unread_message_count');
        return self::query()->insert($data);
    }

}
