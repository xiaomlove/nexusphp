<?php

namespace App\Models;


class Thank extends NexusModel
{
    protected $fillable = ['torrentid', 'userid'];

    public function user()
    {
        return $this->belongsTo(User::class, 'userid');
    }

    public function torrent()
    {
        return $this->belongsTo(Torrent::class. 'torrentid');
    }
}
