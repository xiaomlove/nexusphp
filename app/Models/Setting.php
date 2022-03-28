<?php

namespace App\Models;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Nexus\Database\NexusDB;

class Setting extends NexusModel
{
    protected $fillable = ['name', 'value'];

    public static function get($name = null)
    {
        $settings = NexusDB::remember("nexus_settings_in_laravel", 10, function () {
            $rows = self::query()->get(['name', 'value']);
            $result = [];
            foreach ($rows as $row) {
                $value = $row->value;
                if (!is_null($value)) {
                    $arr = json_decode($value, true);
                    if (is_array($arr)) {
                        $value = $arr;
                    }
                }
                Arr::set($result, $row->name, $value);
            }
            return $result;
        });
        if (is_null($name)) {
            return $settings;
        }
        return Arr::get($settings, $name);
    }

}
