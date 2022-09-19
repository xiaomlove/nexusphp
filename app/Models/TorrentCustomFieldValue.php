<?php

namespace App\Models;

use Nexus\Database\NexusDB;

class TorrentCustomFieldValue extends NexusModel
{
    protected $table = 'torrents_custom_field_values';

    public $timestamps = true;

    protected $fillable = ['torrent_id', 'custom_field_id', 'custom_field_value', ];

}
