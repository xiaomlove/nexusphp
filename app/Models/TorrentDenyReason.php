<?php

namespace App\Models;

use Nexus\Database\NexusDB;

class TorrentDenyReason extends NexusModel
{
    protected $table = 'torrent_deny_reasons';

    public $timestamps = true;

    protected $fillable = ['name', 'hits', 'priority',];

}
