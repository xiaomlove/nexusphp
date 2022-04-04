<?php

namespace App\Models;

class Attendance extends NexusModel
{
    protected $table = 'attendance';

    protected $fillable = ['uid', 'added', 'points', 'days', 'total_days'];

    protected $casts = [
        'added' => 'datetime',
    ];

    const INITIAL_BONUS = 10;
    const STEP_BONUS = 5;
    const MAX_BONUS = 1000;
    const CONTINUOUS_BONUS = [
        10 => 200,
        20 => 500,
        30 => 1000
    ];

    const MAX_RETROACTIVE_DAYS = 30;


    public function logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'uid', 'uid');
    }

}
