<?php

namespace App\Models;

class TorrentTag extends NexusModel
{
    public $timestamps = true;

    protected $fillable = [
        'torrent_id', 'tag_id', 'priority'
    ];


}
