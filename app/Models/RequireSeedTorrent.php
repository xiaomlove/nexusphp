<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequireSeedTorrent extends NexusModel
{
    protected $fillable = [
        'torrent_id',
        'original_sp_state',
        'original_promotion_time_type',
        'original_promotion_until',
    ];

    public $timestamps = true;
}
