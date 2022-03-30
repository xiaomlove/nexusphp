<?php

namespace App\Models;


class Request extends NexusModel
{
    protected $fillable = ['userid', 'request', 'descr', 'comments', 'hits', 'added'];

    protected $casts = [
        'added' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userid');
    }

}
