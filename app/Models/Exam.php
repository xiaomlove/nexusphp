<?php

namespace App\Models;

class Exam extends NexusModel
{
    protected $fillable = ['name', 'description', 'begin', 'end', 'status'];

    const STATUS_ENABLED = 0;
    const STATUS_DISABLED = 1;

    public static $status = [
        self::STATUS_ENABLED => ['text' => '启用中'],
        self::STATUS_DISABLED => ['text' => '已禁用'],
    ];

    const INDEX_UPLOADED = 1;
    const INDEX_SEED_TIME = 2;
    const INDEX_DOWNLOADED = 3;
    const INDEX_LEECH_TIME = 4;
    const INDEX_BONUS = 5;

    public static $indexes = [
        self::INDEX_UPLOADED => ['text' => 'Uploaded'],
        self::INDEX_SEED_TIME => ['text' => 'Seed time'],
        self::INDEX_DOWNLOADED => ['text' => 'Download'],
        self::INDEX_LEECH_TIME => ['text' => 'Leech time'],
        self::INDEX_BONUS => ['text' => 'Bonus'],
    ];

    public function getStatusTextAttribute()
    {
        return self::$status[$this->status]['text'] ?? '';
    }

}
