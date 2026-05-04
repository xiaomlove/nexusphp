<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reward extends NexusModel
{
    protected $table = 'magic';

    protected $fillable = ['torrentid', 'userid', 'value'];

    public $timestamps = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
