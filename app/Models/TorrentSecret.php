<?php

namespace App\Models;

class TorrentSecret extends NexusModel
{
    protected $fillable = ['uid', 'torrent_id', 'secret'];
}
