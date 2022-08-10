<?php

namespace App\Models;

class UsernameChangeLog extends NexusModel
{
    protected $fillable = ['uid', 'username_old', 'username_new', ];

    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(User::class, 'uid');
    }

}
