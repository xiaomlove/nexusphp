<?php

namespace App\Models;

class HitAndRun extends NexusModel
{
    protected $table = 'hit_and_runs';

    protected $fillable = ['uid', 'snatch_id', 'torrent_id', 'status', 'comment'];

    public $timestamps = true;

    const STATUS_INSPECTING = 1;
    const STATUS_REACHED = 2;
    const STATUS_UNREACHED = 3;
    const STATUS_PARDONED = 4;

    public static $status = [
        self::STATUS_INSPECTING => ['text' => 'Inspecting'],
        self::STATUS_REACHED => ['text' => 'Reached'],
        self::STATUS_UNREACHED => ['text' => 'Unreached'],
        self::STATUS_PARDONED => ['text' => 'Pardoned'],
    ];

    const MODE_DISABLED = 'disabled';
    const MODE_MANUAL = 'manual';
    const MODE_GLOBAL = 'global';

    public static $modes = [
        self::MODE_DISABLED => ['text' => 'Disabled'],
        self::MODE_MANUAL => ['text' => 'Manual'],
        self::MODE_GLOBAL => ['text' => 'Global'],
    ];

    const MINIMUM_IGNORE_USER_CLASS = User::CLASS_VIP;

    public function getStatusTextAttribute()
    {
        return nexus_trans('hr.status_' . $this->status);
    }

    public static function listStatus(): array
    {
        $result = self::$status;
        foreach ($result as $key => &$value) {
            $value['text'] = nexus_trans('hr.status_' . $key);
        }
        return $result;
    }

    public static function listModes(): array
    {
        $result = self::$modes;
        foreach ($result as $key => &$value) {
            $value['text'] = nexus_trans('hr.mode_' . $key);
        }
        return $result;
    }

    public static function getIsEnabled(): bool
    {
        $result = Setting::get('hr.mode');
        return $result && in_array($result, [self::MODE_GLOBAL, self::MODE_MANUAL]);
    }

    public function torrent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Torrent::class, 'torrent_id');
    }

    public function snatch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Snatch::class, 'snatched_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }


}
