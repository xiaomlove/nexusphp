<?php

namespace App\Models;


class Post extends NexusModel
{
    public function user()
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
