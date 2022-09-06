<?php

namespace App\Models;


class TorrentCustomFieldValue extends NexusModel
{
    protected $table = 'torrents_custom_field_values';

    protected $fillable = [
        'torrent_id', 'custom_field_id', 'custom_field_value',
    ];
}
