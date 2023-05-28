<?php
namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

class SettingRepository extends BaseRepository
{
    public function getList(array $params)
    {
        $results = Setting::getFromDb();
        $prefix = $params['prefix'] ?? null;
        if ($prefix) {
            return [$prefix => Arr::get($results, $prefix, [])];
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
        NexusDB::cache_del("nexus_settings_in_laravel");
        NexusDB::cache_del("nexus_settings_in_nexus");
        return $result;
    }

}
