<?php
namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SettingRepository extends BaseRepository
{
    public function getList(array $params)
    {
        $query = Setting::query();
        if (!empty($params['prefix'])) {
            $query->where('name', 'like', "{$params['prefix']}%");
        }
        $settings =  $query->get();
        $results = [];
        foreach ($settings as $setting) {
            $value = $setting->value;
            $arr = json_decode($value, true);
            if (is_array($arr)) {
                $value = $arr;
            }
            Arr::set($results, $setting->name, $value);
        }
        return $results;
    }

    public function store(array $params)
    {
        $settingModel = new Setting();
        $values = [];
        foreach ($params as $prefix => $nameValues) {
            if (!is_array($nameValues)) {
                throw new \InvalidArgumentException("Unsupported parameter format.");
            }
            foreach ($nameValues as $name => $value) {
                $valueArr = Arr::wrap($value);
                array_walk_recursive($valueArr, function ($item) {return addslashes($item);});
                if (is_array($value)) {
                    $valueStr = json_encode($valueArr);
                } else {
                    $valueStr = Arr::first($valueArr);
                }
                $values[] = sprintf("('%s', '%s')", addslashes("$prefix.$name"), addslashes($valueStr));
            }
        }
        if (empty($values)) {
            do_log("no values");
            return true;
        }
        $sql = sprintf(
            "insert into `%s` (`name`, `value`) values %s on duplicate key update `value` = values(`value`)",
            $settingModel->getTable(), implode(', ', $values)
        );
        $result = DB::insert($sql);
        do_log("sql: $sql, result: $result");
        return $result;
    }

}
