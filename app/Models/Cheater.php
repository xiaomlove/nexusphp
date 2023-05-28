<?php

namespace App\Models;


class Cheater extends NexusModel
{
    protected $fillable = [
        'added', 'userid', 'torrentid', 'uploaded', 'downloaded', 'anctime', 'seeders', 'leechers', 'hit',
        'dealtby', 'dealtwith', 'comment',
    ];
}
