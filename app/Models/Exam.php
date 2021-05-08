<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends NexusModel
{
    protected $fillable = ['name', 'description', 'begin', 'end', 'duration', 'status', 'is_discovered', 'filters', 'indexes'];

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

    const DISCOVERED_YES = 1;
    const DISCOVERED_NO = 0;

    public static $discovers = [
        self::DISCOVERED_NO => ['text' => 'No'],
        self::DISCOVERED_YES => ['text' => 'Yes'],
    ];

    const INDEX_UPLOADED = 1;
    const INDEX_SEED_TIME_AVERAGE = 2;
    const INDEX_DOWNLOADED = 3;
    const INDEX_SEED_BONUS = 4;

    public static $indexes = [
        self::INDEX_UPLOADED => ['name' => 'Uploaded', 'unit' => 'GB'],
        self::INDEX_SEED_TIME_AVERAGE => ['name' => 'Seed time average', 'unit' => 'Hour'],
        self::INDEX_DOWNLOADED => ['name' => 'Downloaded', 'unit' => 'GB'],
        self::INDEX_SEED_BONUS => ['name' => 'Seed bonus', 'unit' => ''],
    ];

    const FILTER_USER_CLASS = 'classes';
    const FILTER_USER_REGISTER_TIME_RANGE = 'register_time_range';

    public static $filters = [
        self::FILTER_USER_CLASS => ['name' => 'User class'],
        self::FILTER_USER_REGISTER_TIME_RANGE => ['name' => 'User register time range'],
    ];

    protected static function booted()
    {
        static::saving(function (Model $model) {
            $model->duration = (int)$model->duration;
        });
    }

    public function getStatusTextAttribute(): string
    {
        return self::$status[$this->status]['text'] ?? '';
    }

    public function getIsDiscoveredTextAttribute(): string
    {
        return self::$discovers[$this->is_discovered]['text'] ?? '';
    }

    public function getDurationTextAttribute($value): string
    {
        if ($value > 0) {
            return $value . ' Days';
        }
        return '';
    }

}
