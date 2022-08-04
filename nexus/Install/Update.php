<?php

namespace Nexus\Install;

use App\Models\Attendance;
use App\Models\BonusLogs;
use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamUser;
use App\Models\HitAndRun;
use App\Models\Icon;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Torrent;
use App\Models\TorrentTag;
use App\Models\User;
use App\Repositories\AttendanceRepository;
use App\Repositories\BonusRepository;
use App\Repositories\ExamRepository;
use App\Repositories\TagRepository;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Nexus\Database\NexusDB;

class Update extends Install
{

    protected $steps = ['Env check', 'Get files', 'Update .env',  'Perform updates'];

    protected string $lockFile = 'update.lock';


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

    private function addSetting($name, $value)
    {
        $attributes = [
            'name' => $name,
        ];
        $now = Carbon::now()->toDateTimeString();
        $values = [
            'value' => $value,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        return Setting::query()->firstOrCreate($attributes, $values);
    }

    public function runExtraQueries()
    {
        /**
         * @since 1.7.13
         */
        foreach (['adminpanel', 'modpanel', 'sysoppanel'] as $table) {
            $columnInfo = NexusDB::getMysqlColumnInfo($table, 'id');
            if ($columnInfo['DATA_TYPE'] == 'tinyint') {
                sql_query("alter table $table modify id int(11) unsigned not null AUTO_INCREMENT");
            }
        }

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
        if (WITH_LARAVEL && !NexusDB::hasColumn('categories', 'icon_id')) {
            $this->doLog('[INIT CATEGORY ICON_ID]');
            $this->runMigrate('database/migrations/2022_03_08_040415_add_icon_id_to_categories_table.php');
            $icon = Icon::query()->orderBy('id', 'asc')->first();
            if ($icon) {
                Category::query()->where('icon_id', 0)->update(['icon_id' => $icon->id]);
            }
        }
        //fix base url, since beta8
        if (WITH_LARAVEL && NexusDB::hasTable('settings')) {
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
        if (WITH_LARAVEL) {
            if (!NexusDB::hasTable('attendance')) {
                //no table yet, no need to migrate
                $this->runMigrate('database/migrations/2021_06_08_113437_create_attendance_table.php');
            }
            if (!NexusDB::hasColumn('attendance', 'total_days')) {
                $this->runMigrate('database/migrations/2021_06_13_215440_add_total_days_to_attendance_table.php');
                $attendanceRep = new AttendanceRepository();
                $count = $attendanceRep->migrateAttendance();
                $this->doLog("[MIGRATE_ATTENDANCE] $count");
            }
        }

        /**
         * @since 1.6.0-beta13
         *
         * add seed points to user
         */
        if (WITH_LARAVEL && !NexusDB::hasColumn('users', 'seed_points')) {
            $this->runMigrate('database/migrations/2021_06_24_013107_add_seed_points_to_users_table.php');
            //Don't do this, initial seed points = 0;
//            $result = $this->initSeedPoints();
            $this->doLog("[INIT SEED POINTS]");
        }

        /**
         * @since 1.6.0-beta14
         *
         * add id to agent_allowed_exception
         */
        if (WITH_LARAVEL && !NexusDB::hasColumn('agent_allowed_exception', 'id')) {
            $this->runMigrate('database/migrations/2022_02_25_021356_add_id_to_agent_allowed_exception_table.php');
            $this->doLog("[ADD_ID_TO_AGENT_ALLOWED_EXCEPTION]");
        }

        /**
         * @since 1.6.0
         *
         * init tag
         */
        if (WITH_LARAVEL && !NexusDB::hasTable('tags')) {
            $this->runMigrate('database/migrations/2022_03_07_012545_create_tags_table.php');
            $this->initTag();
            $this->doLog("[INIT_TAG]");
        }

        /**
         * @since 1.6.3
         *
         * add usersearch.php and unco.php
         */
        $menus = [
            ['name' => 'Search user', 'url' => 'usersearch.php', 'info' => 'Search user'],
            ['name' => 'Confirm user', 'url' => 'unco.php', 'info' => 'Confirm user to complete registration'],
        ];
        $table = 'modpanel';
        foreach ($menus as $menu) {
            $count = get_row_count($table, "where url = " . sqlesc($menu['url']));
            if ($count == 0) {
                $id = NexusDB::insert($table, $menu);
                $this->doLog("[ADD MENU] insert: " . json_encode($menu) . " to table: $table, id: $id");
            }
        }

        /**
         * @since 1.7.0
         *
         * add attendance_card to users
         */
        if (WITH_LARAVEL && !NexusDB::hasColumn('users', 'attendance_card')) {
            $this->runMigrate('database/migrations/2022_04_02_163930_create_attendance_logs_table.php');
            $this->runMigrate('database/migrations/2022_04_03_041642_add_attendance_card_to_users_table.php');
            $rep = new AttendanceRepository();
            $count = $rep->migrateAttendanceLogs();
            $this->doLog("[ADD_ATTENDANCE_CARD_TO_USERS], migrateAttendanceLogs: $count");
        }

        /**
         * @since 1.7.12
         */
        $menus = [
            ['name' => 'Add Bonus/Attend card/Invite/upload', 'url' => 'increment-bulk.php', 'info' => 'Add Bonus/Attend card/Invite/upload to certain classes'],
        ];
        $table = 'sysoppanel';
        $this->addMenu($table, $menus);
        $menuToDel = ['amountupload.php', 'amountattendancecard.php', 'amountbonus.php', 'deletedisabled.php'];
        $this->removeMenu($menuToDel);

        /**
         * @since 1.7.19
         */
        $this->removeMenu(['freeleech.php']);
        NexusDB::cache_del('nexus_rss');
        NexusDB::cache_del('nexus_is_ip_seed_box');

    }

    public function runExtraMigrate()
    {
        if (!WITH_LARAVEL) {
            $this->doLog(__METHOD__ . ", laravel is not available");
            return;
        }
        if (NexusDB::hasColumn('torrents', 'tags')) {
            if (Torrent::query()->where('tags', '>', 0)->count() > 0 && TorrentTag::query()->count() == 0) {
                $this->doLog("[MIGRATE_TORRENT_TAG]...");
                $tagRep = new TagRepository();
                $tagRep->migrateTorrentTag();
                $this->doLog("[MIGRATE_TORRENT_TAG] done!");
            }
            $sql = 'alter table torrents drop column tags';
            sql_query($sql);
            $this->doLog($sql);
        } else {
            $this->doLog("torrents table does not has column: tags");
        }

    }

    private function addMenu($table, array $menus)
    {
        foreach ($menus as $menu) {
            $count = get_row_count($table, "where url = " . sqlesc($menu['url']));
            if ($count == 0) {
                $id = NexusDB::insert($table, $menu);
                $this->doLog("[ADD MENU] insert: " . json_encode($menu) . " to table: $table, id: $id");
            }
        }
    }

    private function removeMenu(array $menus, array $tables = ['sysoppanel', 'adminpanel', 'modpanel'])
    {
        if (empty($menus)) {
            return;
        }
        foreach ($tables as $table) {
            NexusDB::table($table)->whereIn('url', $menus)->delete();
        }
    }


    public function listVersions()
    {
        $url = "https://api.github.com/repos/xiaomlove/nexusphp/releases";
        $versions = $this->requestGithub($url);
        return array_reverse($versions);
    }

    public function getLatestCommit()
    {
        $url = "https://api.github.com/repos/xiaomlove/nexusphp/commits/php8";
        return $this->requestGithub($url);
    }

    public function requestGithub($url)
    {
        $client = new Client();
        $logPrefix = "Request github: $url";
        $response = $client->get($url, ['timeout' => 10,]);
        if (($statusCode = $response->getStatusCode()) != 200) {
            throw new \RuntimeException("$logPrefix fail, status code：$statusCode");
        }
        if ($response->getBody()->getSize() <= 0) {
            throw new \RuntimeException("$logPrefix fail, response empty");
        }
        $bodyString = $response->getBody()->getContents();
        $this->doLog("[REQUEST_GITHUB_RESPONSE]: $bodyString");
        $results = json_decode($bodyString, true);
        if (empty($results) || !is_array($results)) {
            throw new \RuntimeException("$logPrefix response invalid");
        }
        return $results;
    }

    public function downAndExtractCode($url, array $includes = []): string
    {
        $requireCommand = 'rsync';
        if (!command_exists($requireCommand)) {
            throw new \RuntimeException("command: $requireCommand not exists!");
        }
        $arr = explode('/', $url);
        $basename = last($arr);
        $isZip = false;
        if (Str::contains($basename,'.zip')) {
            $isZip = true;
            $basename = strstr($basename, '.zip', true);
            $suffix = ".zip";
        } else {
            $suffix = '.tar.gz';
        }
        $filename = sprintf('%s/nexusphp-%s-%s%s', sys_get_temp_dir(), $basename, date('YmdHis'), $suffix);
        $this->doLog("download from: $url, save to filename: $filename");
        $client = new Client();
        $response = $client->request('GET', $url, ['sink' => $filename]);
        if (($statusCode = $response->getStatusCode()) != 200) {
            throw new \RuntimeException("Download fail, status code：$statusCode");
        }
        if (($bodySize = $response->getBody()->getSize()) <= 0) {
            throw new \RuntimeException("Download fail, file size：$bodySize");
        }
        if (!file_exists($filename)) {
            throw new \RuntimeException("Download fail, file not exists：$filename");
        }
        if (filesize($filename) <= 0) {
            throw new \RuntimeException("Download fail, file: $filename size = 0");
        }
        $this->doLog('SUCCESS_DOWNLOAD');
        $extractDir = str_replace($suffix, "", $filename);
        $command = "mkdir -p $extractDir";
        $this->executeCommand($command);

        if ($isZip) {
            $command = "unzip -q $filename -d $extractDir";
        } else {
            $command = "tar -xf $filename -C $extractDir";
        }
        $this->executeCommand($command);

        foreach (glob("$extractDir/*") as $path) {
            if (is_dir($path)) {
                $excludes = ['.git', 'public/favicon.ico', '.env'];
                if (!in_array('composer', $includes)) {
                    $excludes[] = 'composer.lock';
                    $excludes[] = 'composer.json';
                }
//                $command = sprintf('cp -raf %s/. %s', $path, ROOT_PATH);
                $command = "rsync -rvq $path/ " . ROOT_PATH;
                foreach ($excludes as $exclude) {
                    $command .= " --exclude=$exclude";
                }
                $this->executeCommand($command);
                break;
            }
        }
        $this->doLog('SUCCESS_EXTRACT');
        return $extractDir;
    }

    public function initSeedPoints(): int
    {
        $size = 10000;
        $tableName = (new User())->getTable();
        $result = 0;
        do {
            $affectedRows = NexusDB::table($tableName)
                ->whereNull('seed_points')
                ->limit($size)
                ->update([
                    'seed_points' => NexusDB::raw('seedbonus')
                ]);
            $result += $affectedRows;
            $this->doLog("affectedRows: $affectedRows, query: " . last_query());
        } while ($affectedRows > 0);

        return $result;
    }

    public function updateDependencies()
    {
        $command = "composer install -d " . ROOT_PATH;
        $this->executeCommand($command);
        $this->doLog("[COMPOSER INSTALL] SUCCESS");
    }

    public function initTag()
    {
        $priority = count(Tag::DEFAULTS);
        $dateTimeStringNow = date('Y-m-d H:i:s');
        foreach (Tag::DEFAULTS as $value) {
            $attributes = [
                'name' => $value['name'],
            ];
            $values = [
                'priority' => $priority,
                'color' => $value['color'],
                'created_at' => $dateTimeStringNow,
                'updated_at' => $dateTimeStringNow,
            ];
            Tag::query()->firstOrCreate($attributes, $values);
            $priority--;
        }
    }



}
