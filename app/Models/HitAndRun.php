<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidArgumentException;
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

    public static array $status = [
        self::STATUS_INSPECTING => ['text' => 'Inspecting'],
        self::STATUS_REACHED => ['text' => 'Reached'],
        self::STATUS_UNREACHED => ['text' => 'Unreached'],
        self::STATUS_PARDONED => ['text' => 'Pardoned'],
    ];

    const CAN_PARDON_STATUS = [
        self::STATUS_INSPECTING,
        self::STATUS_UNREACHED,
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
            get: fn($value, $attributes) => $this->doGetSeedTimeRequired()
        );
    }

    protected function inspectTimeLeft(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => $this->doGetInspectTimeLeft()
        );
    }

    private function doGetInspectTimeLeft(): string
    {
        if ($this->status != self::STATUS_INSPECTING) {
            return '---';
        }
        $searchBoxId = $this->torrent->basic_category->mode ?? 0;
        if ($searchBoxId == 0) {
            do_log(sprintf('[INVALID_CATEGORY], Torrent: %s', $this->torrent_id), 'error');
            return '---';
        }
        $inspectTime = HitAndRun::getConfig('inspect_time', $searchBoxId);
        $diffInSeconds = Carbon::now()->diffInSeconds($this->snatch->completedat->addHours($inspectTime));
        return mkprettytime($diffInSeconds);
    }

    private function doGetSeedTimeRequired(): string
    {
        if ($this->status != self::STATUS_INSPECTING) {
            return '---';
        }
        $searchBoxId = $this->torrent->basic_category->mode ?? 0;
        if ($searchBoxId == 0) {
            do_log(sprintf('[INVALID_CATEGORY], Torrent: %s', $this->torrent_id), 'error');
            return '---';
        }
        $seedTimeMinimum = HitAndRun::getConfig('seed_time_minimum', $searchBoxId);
        $diffInSeconds = 3600 * $seedTimeMinimum - $this->snatch->seedtime;
        return mkprettytime($diffInSeconds);
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
        $enableSpecialSection = Setting::get('main.spsct') == 'yes';
        $browseMode = self::getConfig('mode', Setting::get('main.browsecat'));
        $browseEnabled = $browseMode && in_array($browseMode, [self::MODE_GLOBAL, self::MODE_MANUAL]);
        if (!$enableSpecialSection) {
            do_log("Not enable special section, browseEnabled: $browseEnabled");
            return $browseEnabled;
        }
        $specialMode = self::getConfig('mode', Setting::get('main.specialcat'));
        $specialEnabled =  $specialMode && in_array($specialMode, [self::MODE_GLOBAL, self::MODE_MANUAL]);
        $result = $browseEnabled || $specialEnabled;
        do_log("Enable special section, browseEnabled: $browseEnabled, specialEnabled: $specialEnabled, result: $result");
        return $result;
    }

    public static function getConfig($name, $searchBoxId)
    {
        if ($name == '*') {
            $key = "hr";
        } else {
            $key = "hr.$name";
        }
        $default = Setting::get($key);
        return apply_filter("nexus_setting_get", $default, $name, ['mode' => $searchBoxId]);
    }

    public static function diffInSection(): bool
    {
        $enableSpecialSection = Setting::get('main.spsct') == 'yes';
        return $enableSpecialSection && apply_filter("hit_and_run_diff_in_section", false);
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
