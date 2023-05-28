<?php

namespace App\Models;

class UserMedal extends NexusModel
{
    protected $fillable = ['uid', 'medal_id', 'expire_at', 'status'];

    const STATUS_NOT_WEARING = 0;
    const STATUS_WEARING = 1;

    public function medal()
    {
        return $this->belongsTo(Medal::class, 'medal_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'uid');
    }

}
