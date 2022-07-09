<?php

namespace App\Models;


class TorrentState extends NexusModel
{
    protected $fillable = ['global_sp_state', 'deadline'];

    protected $table = 'torrents_state';

    public function getGlobalSpStateTextAttribute()
    {
        return Torrent::$promotionTypes[$this->global_sp_state]['text'] ?? '';
    }
}
