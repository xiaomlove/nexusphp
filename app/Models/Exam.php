<?php

namespace App\Models;

class Exam extends NexusModel
{
    protected $fillable = ['name', 'description', 'begin', 'end', 'status', 'filters', 'indexes'];

    public $timestamps = true;

    protected $casts = [
        'filters' => 'object',
        'indexes' => 'array',
    ];

    const STATUS_ENABLED = 0;
    const STATUS_DISABLED = 1;

    public static $status = [
        self::STATUS_ENABLED => ['text' => 'Enabled'],
        self::STATUS_DISABLED => ['text' => 'Disabled'],
    ];

    const INDEX_UPLOADED = 1;
    const INDEX_SEED_TIME_AVERAGE = 2;
    const INDEX_DOWNLOADED = 3;
    const INDEX_BONUS = 4;

    public static $indexes = [
        self::INDEX_UPLOADED => ['name' => 'Uploaded', 'unit' => 'GB'],
        self::INDEX_SEED_TIME_AVERAGE => ['name' => 'Seed Time Average', 'unit' => 'Hour'],
        self::INDEX_DOWNLOADED => ['name' => 'Downloaded', 'unit' => 'GB'],
        self::INDEX_BONUS => ['name' => 'Bonus', 'unit' => ''],
    ];

    const FILTER_USER_CLASS = 'classes';
    const FILTER_USER_REGISTER_TIME_RANGE = 'register_time_range';

    public static $filters = [
        self::FILTER_USER_CLASS => ['name' => 'User Class'],
        self::FILTER_USER_REGISTER_TIME_RANGE => ['name' => 'User Register Time Range'],
    ];

    public function getStatusTextAttribute(): string
    {
        return self::$status[$this->status]['text'] ?? '';
    }

}
