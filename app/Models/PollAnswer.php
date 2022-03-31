<?php

namespace App\Models;


class PollAnswer extends NexusModel
{
    protected $table = 'pollanswers';

    protected $fillable = ['pollid', 'userid', 'selection',];

    public function poll()
    {
        return $this->belongsTo(Poll::class, 'pollid');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userid');
    }

}
