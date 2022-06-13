<?php

namespace App\Models;

class StaffMessage extends NexusModel
{
    protected $table = 'staffmessages';

    protected $fillable = [
        'sender', 'added', 'subject', 'msg', 'answeredby', 'answered', 'answer'
    ];

    protected $casts = [
        'added' => 'datetime',
    ];

    public function send_user()
    {
        return $this->belongsTo(User::class, 'sender')->withDefault(['id' => 0, 'username' => 'System']);
    }

    public function answer_user()
    {
        return $this->belongsTo(User::class, 'answeredby');
    }

}
