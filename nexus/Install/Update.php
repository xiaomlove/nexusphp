<?php

namespace Nexus\Install;

use App\Models\Attendance;
use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamUser;
use App\Models\Icon;
use App\Models\Setting;
use App\Repositories\ExamRepository;
use Illuminate\Support\Str;
use Nexus\Database\NexusDB;

class Update extends Install
{

    protected $steps = ['环境检测', '添加 .env 文件', '修改&创建数据表', '导入数据'];


    public function getLogFile()
    {
        return sprintf('%s/nexus-update-%s.log', sys_get_temp_dir(), date('Ymd'));
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
            $id = NexusDB::insert($table, $insert);
            $this->doLog("[ADD CUSTOM FIELD MENU] insert: " . json_encode($insert) . " to table: $table, id: $id");
        }
        //since beta8
        if (WITH_LARAVEL && NexusDB::schema()->hasColumn('categories', 'icon_id')) {
            $this->doLog('[INIT CATEGORY ICON_ID]');
            $icon = Icon::query()->orderBy('id', 'asc')->first();
            if ($icon) {
                Category::query()->where('icon_id', 0)->update(['icon_id' => $icon->id]);
            }
        }
        //fix base url, since beta8
        if (WITH_LARAVEL && NexusDB::schema()->hasTable('settings')) {
            $settingBasic = get_setting('basic');
            if (isset($settingBasic['BASEURL']) && Str::startsWith($settingBasic['BASEURL'], 'localhost')) {
                $this->doLog('[RESET CONFIG basic.BASEURL]');
                Setting::query()->where('name', 'basic.BASEURL')->update(['value' => '']);
            }
            if (isset($settingBasic['announce_url']) && Str::startsWith($settingBasic['announce_url'], 'localhost')) {
                $this->doLog('[RESET CONFIG basic.announce_url]');
                Setting::query()->where('name', 'basic.announce_url')->update(['value' => '']);
            }
        }

        //torrent support sticky second level
        if (WITH_LARAVEL) {
            $columnInfo = NexusDB::getMysqlColumnInfo('torrents', 'pos_state');
            $this->doLog("[TORRENT POS_STATE], column info: " . json_encode($columnInfo));
            if ($columnInfo['DATA_TYPE'] == 'enum') {
                $sql = "alter table torrents modify `pos_state` varchar(32) NOT NULL DEFAULT 'normal'";
                $this->doLog("[ALTER TORRENT POS_STATE TYPE TO VARCHAR], $sql");
                sql_query($sql);
            }
        }

        /**
         * @since 1.6.0-beta9
         *
         * attendance change, do migrate
         */
        if (WITH_LARAVEL && VERSION_NUMBER == '1.6.0-beta9' && NexusDB::schema()->hasColumn('attendance', 'total_days')) {
            $this->migrateAttendance();
        }
    }

    private function migrateAttendance()
    {
        $page = 1;
        $size = 1000;
        while (true) {
            $logPrefix = "[MIGRATE_ATTENDANCE], page: $page, size: $size";
            $result = Attendance::query()
                ->groupBy(['uid'])
                ->selectRaw('uid, max(id) as id, count(*) as counts')
                ->forPage($page, $size)
                ->get();
            $this->doLog("$logPrefix, " . last_query() . ", count: " . $result->count());
            if ($result->isEmpty()) {
                $this->doLog("$logPrefix, no more data...");
                break;
            }
            foreach ($result as $row) {
                $update = [
                    'total_days' => $row->counts,
                ];
                $updateResult = $row->update($update);
                $this->doLog(sprintf(
                    "$logPrefix, update user: %s(ID: %s) => %s, result: %s",
                    $row->uid, $row->id, json_encode($update), var_export($updateResult, true)
                ));
            }
            $page++;
        }

    }

}
