<?php

namespace App\Models;

class UserBanLog extends NexusModel
{
    protected $table = 'user_ban_logs';

    protected $fillable = ['uid', 'username', 'operator', 'reason'];
}
