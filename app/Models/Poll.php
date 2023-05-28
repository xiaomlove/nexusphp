<?php

namespace App\Models;


class Poll extends NexusModel
{
    protected $fillable = ['added', 'question', 'option0', 'option1', 'option2', 'option3', 'option4', 'option5'];

    protected $casts = [
        'added' => 'datetime'
    ];

    const MAX_OPTION_INDEX = 19;

    public function answers()
    {
        return $this->hasMany(PollAnswer::class, 'pollid');
    }

}
