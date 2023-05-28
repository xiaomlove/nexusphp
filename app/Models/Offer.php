<?php

namespace App\Models;


class Offer extends NexusModel
{
    protected $fillable = ['userid', 'name', 'descr', 'comments', 'added'];

    protected $casts = [
        'added' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userid');
    }

}
