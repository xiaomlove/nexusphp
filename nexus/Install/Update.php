<?php

namespace Nexus\Install;

use App\Models\Category;
use App\Models\Icon;
use Nexus\Database\DB;

class Update extends Install
{

    protected $steps = ['环境检测', '添加 .env 文件', '修改&创建数据表', '导入数据'];


    public function getLogFile()
    {
        return sprintf('%s/nexus-update-%s.log', sys_get_temp_dir(), date('YmdHis'));
    }

    public function getUpdateDirectory()
    {
        return ROOT_PATH . 'public/update';
    }

    public function listTableFieldsFromCreateTable($createTableSql)
    {
        $arr = preg_split("/[\r\n]+/", $createTableSql);
        $result = [];
        foreach ($arr as $value) {
            $value = trim($value);
            if (substr($value, 0, 1) != '`') {
                continue;
            }
            $pos = strpos($value, '`', 1);
            $field = substr($value, 1, $pos - 1);
            $result[$field] = rtrim($value, ',');
        }
        return $result;
    }

    public function listTableFieldsFromDb($table)
    {
        $sql = "desc $table";
        $res = sql_query($sql);
        $data = [];
        while ($row = mysql_fetch_assoc($res)) {
            $data[$row['Field']] = $row;
        }
        return $data;
    }

    public function runExtraQueries()
    {
        //custom field menu
        $url = 'fields.php';
        $table = 'adminpanel';
        $count = get_row_count($table, "where url = " . sqlesc($url));
        if ($count == 0) {
            $insert = [
                'name' => 'Custom Field Manage',
                'url' => $url,
                'info' => 'Manage custom fields',
            ];
            $id = DB::insert($table, $insert);
            $this->doLog("[ADD CUSTOM FIELD MENU] insert: " . json_encode($insert) . " to table: $table, id: $id");
        }
        if (WITH_LARAVEL && DB::schema()->hasColumn('categories', 'icon_id')) {
            $icon = Icon::query()->orderBy('id', 'asc')->first();
            if ($icon) {
                Category::query()->where('icon_id', 0)->update(['icon_id' => $icon->id]);
            }
        }
    }

}
