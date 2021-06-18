<?php

namespace App\Models;

class HitAndRun extends NexusModel
{
    protected $table = 'hit_and_runs';

    protected $fillable = ['uid', 'peer_id', 'torrent_id', 'status', 'comment'];

    public $timestamps = true;

    const STATUS_INSPECTING = 1;
    const STATUS_PASSED = 2;
    const STATUS_NOT_PASSED = 3;
    const STATUS_CANCELED = 4;

    public static $status = [
        self::STATUS_INSPECTING => ['text' => '考察中'],
        self::STATUS_PASSED => ['text' => '已通过'],
        self::STATUS_NOT_PASSED => ['text' => '未通过'],
        self::STATUS_CANCELED => ['text' => '已取消'],
    ];

    public function getStatusTextAttribute()
    {
        return self::$status[$this->status] ?? '';
    }


}
