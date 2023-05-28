<?php

namespace App\Models;

class AttendanceLog extends NexusModel
{
    protected $table = 'attendance_logs';

    protected $fillable = ['uid', 'points', 'date', 'is_retroactive'];

    public $timestamps = true;

}
