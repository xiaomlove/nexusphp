<?php

namespace App\Models;

class Attendance extends NexusModel
{
    protected $table = 'attendance';

    protected $fillable = ['uid', 'added', 'points', 'days', 'total_days'];

    protected $casts = [
        'added' => 'datetime',
    ];
}
