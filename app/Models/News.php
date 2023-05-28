<?php

namespace App\Models;

class News extends NexusModel
{
    protected $table = 'news';

    protected $fillable = [
        'userid', 'added', 'title', 'body', 'notify',
    ];

    protected $casts = [
        'added' => 'datetime',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }


}
