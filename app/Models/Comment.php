<?php

namespace App\Models;


class Comment extends NexusModel
{
    protected $casts = [
        'added' => 'datetime',
        'editdate' => 'datetime',
    ];

    public function related_torrent()
    {
        return $this->belongsTo(Torrent::class, 'torrent');
    }

    public function create_user()
    {
        return $this->belongsTo(User::class, 'user')->withDefault(User::getDefaultUserAttributes());
    }

    public function update_user()
    {
        return $this->belongsTo(User::class, 'editedby')->withDefault(User::getDefaultUserAttributes());
    }
}
