<?php

namespace App\Models;


class Bookmark extends NexusModel
{
    protected $table = 'bookmarks';

    protected $fillable = ['userid', 'torrentid'];

    public function torrent()
    {
        return $this->belongsTo(Torrent::class, 'torrentid');
    }

    public function user()
    {
        return $this->belongsTo(Torrent::class, 'userid');
    }
}
