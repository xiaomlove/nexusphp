<?php

namespace App\Models;

use Illuminate\Support\Arr;
use Nexus\Database\NexusDB;

class Setting extends NexusModel
{
    protected $fillable = ['name', 'value', 'autoload'];

    public $timestamps = true;

    const PERMISSION_NO_CLASS = 100;

    public static array $permissionMustHaveClass = ['defaultclass', 'staffmem'];

    const DIRECT_PERMISSION_CACHE_KEY_PREFIX = 'nexus_direct_permissions_';
    const ROLE_PERMISSION_CACHE_KEY_PREFIX = 'nexus_role_permissions_';

    const TORRENT_GLOBAL_STATE_CACHE_KEY = 'global_promotion_state';

    /**
     * get setting autoload = yes with cache
     *
     * @param null $name
     * @param null $default
     * @return mixed
     */
    public static function get($name = null, $default = null): mixed
    {
        static $settings = null;
        if (is_null($settings)) {
            $settings = NexusDB::remember("nexus_settings_in_laravel", 600, function () {
                return self::getFromDb();
            });
        }
        if (is_null($name)) {
            return $settings;
        }
        return Arr::get($settings, $name, $default);
    }

    /**
     * get setting autoload = yes without cache
     *
     * @param null $name
     * @param null $default
     * @return mixed
     */
    public static function getFromDb($name = null, $default = null): mixed
    {
        $rows = self::query()->where('autoload', 'yes')->get(['name', 'value']);
        $result = [];
        foreach ($rows as $row) {
            $value = self::normalizeValue($row);
            Arr::set($result, $row->name, $value);
        }
        if (is_null($name)) {
            return $result;
        }
        return Arr::get($result, $name, $default);
    }

    /**
     * get from db by name, generally used for `autoload` = 'no'
     *
     * @param $name
     * @param null $default
     * @return mixed
     */
    public static function getByName($name, $default = null): mixed
    {
        $result = self::query()->where('name', $name)->first();
        if ($result) {
            return self::normalizeValue($result);
        }
        return $default;
    }

    public static function getByWhereRaw($whereRaw): array
    {
        $result = [];
        $list = self::query()->whereRaw($whereRaw)->get();
        foreach ($list as $value) {
            Arr::set($result, $value->name, self::normalizeValue($value));
        }
        return $result;
    }

    public static function normalizeValue(Setting $setting)
    {
        $value = $setting->value;
        if (!is_null($value)) {
            $arr = json_decode($value, true);
            if (is_array($arr)) {
                $value = $arr;
            }
        }
        return $value;
    }

}
