<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

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

    protected function seedTimeRequired(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => $this->status == self::STATUS_INSPECTING ? mkprettytime(3600 * Setting::get('hr.seed_time_minimum') - $this->snatch->seedtime) : '---'
        );
    }

    protected function inspectTimeLeft(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => $this->status == self::STATUS_INSPECTING ? mkprettytime(\Carbon\Carbon::now()->diffInSeconds($this->snatch->completedat->addHours(Setting::get('hr.inspect_time')))) : '---'
        );
    }

    public function getStatusTextAttribute()
    {
        return nexus_trans('hr.status_' . $this->status);
    }

    public static function listStatus($onlyKeyValue = false): array
    {
        $result = self::$status;
        $keyValues = [];
        foreach ($result as $key => &$value) {
            $text = nexus_trans('hr.status_' . $key);
            $value['text'] = $text;
            $keyValues[$key] = $text;
        }
        if ($onlyKeyValue) {
            return $keyValues;
        }
        return $result;
    }

    public static function listModes($onlyKeyValue = false): array
    {
        $result = self::$modes;
        $keyValues = [];
        foreach ($result as $key => &$value) {
            $text = nexus_trans('hr.mode_' . $key);
            $value['text'] = $text;
            $keyValues[$key] = $text;
        }
        if ($onlyKeyValue) {
            return $keyValues;
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
