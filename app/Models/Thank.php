<?php

namespace App\Models;


class Thank extends NexusModel
{
    public function user()
    {
        return $this->belongsTo(User::class, 'userid');
    }

    public function torrent()
    {
        return $this->belongsTo(Torrent::class. 'torrentid');
    }
}
