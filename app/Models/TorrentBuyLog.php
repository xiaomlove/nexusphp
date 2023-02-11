<?php

namespace App\Models;

use Nexus\Database\NexusDB;

class TorrentBuyLog extends NexusModel
{
    public $timestamps = true;

    protected $fillable = ['uid', 'torrent_id', 'price', 'channel'];

    public function user()
    {
        return $this->belongsTo(User::class, 'uid');
    }

    public function torrent()
    {
        return $this->belongsTo(Torrent::class, 'torrent_id');
    }

}
