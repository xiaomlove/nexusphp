<?php

namespace App\Models;

class Invite extends NexusModel
{
    protected $table = 'invites';

    protected $fillable = [
        'inviter', 'invitee', 'hash', 'time_invited',
    ];
}
