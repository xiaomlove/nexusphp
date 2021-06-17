<?php

namespace Nexus\Install;

use App\Models\Attendance;
use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamUser;
use App\Models\Icon;
use App\Models\Setting;
use App\Repositories\ExamRepository;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Nexus\Database\NexusDB;

class Update extends Install
{

    protected $steps = ['环境检测', '选择版本安装', '更新 .env 文件',  '更新配置'];


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

    public function listVersions()
    {
        $client = new Client();
        $url = "https://api.github.com/repos/xiaomlove/nexusphp/releases";
        $response = $client->get($url, ['timeout' => 10,]);
        if (($statusCode = $response->getStatusCode()) != 200) {
            throw new \RuntimeException("获取版本列表失败，状态码：$statusCode");
        }
        if ($response->getBody()->getSize() <= 0) {
            throw new \RuntimeException("获取版本列表失败，结果为空");
        }
        $bodyString = $response->getBody()->getContents();
        $this->doLog("[LIST_VERSION_RESPONSE]: $bodyString");
        $versions = json_decode($bodyString, true);
        if (empty($versions) || !is_array($versions)) {
            throw new \RuntimeException("获取版本列表结果异常");
        }
        return array_reverse($versions);
    }

    public function downAndExtractCode($url)
    {
        $arr = explode('/', $url);
        $basename = last($arr);
        $suffix = '.tar.gz';
        $filename = sprintf('%s/nexusphp-%s-%s%s', sys_get_temp_dir(), $basename, date('YmdHis'), $suffix);
        $client = new Client();
        $response = $client->request('GET', $url, ['sink' => $filename]);
        if (($statusCode = $response->getStatusCode()) != 200) {
            throw new \RuntimeException("下载错误，状态码：$statusCode");
        }
        if (($bodySize = $response->getBody()->getSize()) <= 0) {
            throw new \RuntimeException("下载错误，文件体积：$bodySize");
        }
        if (!file_exists($filename)) {
            throw new \RuntimeException("下载错误，文件不存在：$filename");
        }
        if (filesize($filename) <= 0) {
            throw new \RuntimeException("下载错误，文件大小为0");
        }
        $this->doLog('SUCCESS_DOWNLOAD');
        $extractDir = str_replace($suffix, "", $filename);
        $command = "mkdir -p $extractDir";
        $result = exec($command, $output, $result_code);
        $this->doLog(sprintf(
            "command: %s, output: %s, result_code: %s, result: %s",
            $command, json_encode($output), $result_code, $result
        ));
        if ($result_code != 0) {
            throw new \RuntimeException("创建解压目录：$extractDir 失败, result_code: $result_code");
        }

        $command = "tar -xf $filename -C $extractDir";
        $result = exec($command, $output, $result_code);
        $this->doLog(sprintf(
            "command: %s, output: %s, result_code: %s, result: %s",
            $command, json_encode($output), $result_code, $result
        ));
        if ($result_code != 0) {
            throw new \RuntimeException("解压文件：$filename 到：$extractDir 失败, result_code: $result_code");
        }

        foreach (glob("$extractDir/*") as $path) {
            if (is_dir($path)) {
                $command = sprintf('cp -Rf %s/* %s', $path, ROOT_PATH);
                $result = exec($command, $output, $result_code);
                $this->doLog(sprintf(
                    "command: %s, output: %s, result_code: %s, result: %s",
                    $command, json_encode($output), $result_code, $result
                ));
                if ($result_code != 0) {
                    throw new \RuntimeException("复制错误, result_code: $result_code");
                }
                break;
            }
        }
        $this->doLog('SUCCESS_EXTRACT');
        return true;
    }

}
