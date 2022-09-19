<?php

namespace App\Models;

use Nexus\Database\NexusDB;

class TorrentCustomField extends NexusModel
{
    protected $table = 'torrents_custom_fields';

    public $timestamps = true;

    protected $fillable = ['name', 'label', 'type', 'required', 'is_single_row', 'options', 'help', 'display', 'priority'];

}
