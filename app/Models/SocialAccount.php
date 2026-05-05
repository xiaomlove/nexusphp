<?php

namespace App\Models;

class SocialAccount extends NexusModel
{
    protected $fillable = [
        'user_id', 'provider_id', 'provider_user_id', 'provider_username', 'provider_email',
    ];

    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
