<?php

namespace App\Models;

class LoginLog extends NexusModel
{
    public $timestamps = true;

    protected $fillable = [
        'uid', 'ip', 'country', 'city', 'client'
    ];


}
