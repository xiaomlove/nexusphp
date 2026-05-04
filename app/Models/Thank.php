<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Thank extends NexusModel
{
    protected $fillable = ['torrentid', 'userid'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }

    public function torrent()
    {
        return $this->belongsTo(Torrent::class.'torrentid');
    }
}
