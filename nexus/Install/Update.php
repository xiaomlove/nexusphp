<?php

namespace Nexus\Install;

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

    }

    public function migrateExamProgress()
    {
        if (!NexusDB::schema()->hasColumn('exam_progress', 'init_value')) {
            sql_query('alter table exam_progress add column `init_value` bigint(20) NOT NULL after `index`');
            $log = 'add column init_value on table exam_progress.';
            $this->doLog($log);
        } else {
            $log = 'column init_value already exists on table exam_progress.';
            $this->doLog($log);
        }
        $examUsersQuery = ExamUser::query()->where('status', ExamUser::STATUS_NORMAL)->with('user');
        $page = 1;
        $size = 100;
        while (true) {
            $examUsers = $examUsersQuery->forPage($page, $size)->get();
            $log = "fetch exam user by: " . last_query();
            $this->doLog($log);
            if ($examUsers->isEmpty()) {
                $log = "no more exam user to handle...";
                $this->doLog($log);
                break;
            }
            $log = 'get init_vlaue...';
            $this->doLog($log);
            foreach ($examUsers as $examUser) {
                $oldProgress = $examUser->progress;
                $user = $examUser->user;
                $currentLogPrefix = "examUser: " . $examUser->toJson();
                $log = sprintf("$currentLogPrefix, progress: %s", json_encode($oldProgress));
                $this->doLog($log);
                foreach ($oldProgress as $index => $progressValue) {
                    if ($index == Exam::INDEX_DOWNLOADED) {
                        $value = $user->downloaded;
                        $initValue = $value - $progressValue;
                    } elseif ($index == Exam::INDEX_UPLOADED) {
                        $value = $user->uploaded;
                        $initValue = $value - $progressValue;
                    } elseif ($index == Exam::INDEX_SEED_BONUS) {
                        $value = $user->seedbonus;
                        $initValue = $value - $progressValue;
                    } elseif ($index == Exam::INDEX_SEED_TIME_AVERAGE) {
                        $value = $progressValue;
                        $initValue = 0;
                    } else {
                        $log = sprintf("$currentLogPrefix, invalid index: %s, skip!", $index);
                        $this->doLog($log);
                        continue;
                    }
                    $insert = [
                        'exam_user_id' => $examUser->id,
                        'exam_id' => $examUser->exam_id,
                        'uid' => $examUser->uid,
                        'index' => $index,
                        'torrent_id' => -1,
                        'value' => $value,
                        'init_value' => $initValue,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];
                    NexusDB::table('exam_progress')->insert($insert);
                    $log = "$currentLogPrefix, insert index: $index progress: " . json_encode($insert);
                    $this->doLog($log);
                }
            }
            $page++;
        }
    }

}
