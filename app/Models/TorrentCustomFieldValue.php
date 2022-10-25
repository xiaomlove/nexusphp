<?php

namespace App\Models;

<<<<<<< HEAD
=======
use Nexus\Database\NexusDB;
>>>>>>> php8

class TorrentCustomFieldValue extends NexusModel
{
    protected $table = 'torrents_custom_field_values';

<<<<<<< HEAD
    protected $fillable = [
        'torrent_id', 'custom_field_id', 'custom_field_value',
    ];
=======
    public $timestamps = true;

    protected $fillable = ['torrent_id', 'custom_field_id', 'custom_field_value', ];

>>>>>>> php8
}
