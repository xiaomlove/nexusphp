<?php

namespace App\Models;

class Attendance extends NexusModel
{
    protected $table = 'attendance';

    protected $casts = [
        'added' => 'datetime',
    ];
}
