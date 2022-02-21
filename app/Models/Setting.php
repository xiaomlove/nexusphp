<?php

namespace App\Models;

use Illuminate\Support\Arr;

class Setting extends NexusModel
{
    protected $fillable = ['name', 'value'];

    public static function get($name = null)
    {
        static $settings;
        if (is_null($settings)) {
            $rows = self::query()->get(['name', 'value']);
            foreach ($rows as $row) {
                $value = $row->value;
                if (!is_null($value)) {
                    $arr = json_decode($value, true);
                    if (is_array($arr)) {
                        $value = $arr;
                    }
                }
                Arr::set($settings, $row->name, $value);
            }
        }
        if (is_null($name)) {
            return $settings;
        }
        return Arr::get($settings, $name);
    }

}
