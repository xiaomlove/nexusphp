<?php

namespace App\Models;

class Invite extends NexusModel
{
    protected $table = 'invites';

    const VALID_YES = 1;
    const VALID_NO = 0;

    public static $validInfo = [
        self::VALID_NO => ['text' => 'No'],
        self::VALID_YES => ['text' => 'Yes'],
    ];

    protected $fillable = [
        'inviter', 'invitee', 'hash', 'time_invited', 'valid',
        'invitee_register_uid', 'invitee_register_email', 'invitee_register_username'
    ];

    public function getValidTextAttribute()
    {
        return self::$validInfo[$this->valid]['text'] ?? '';
    }

    public function inviter_user()
    {
        return $this->belongsTo(User::class, 'inviter');
    }

    public function invitee_user()
    {
        return $this->belongsTo(User::class, 'invitee_register_uid');
    }

}
