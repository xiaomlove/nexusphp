<?php

namespace Nexus\Install;

use Illuminate\Support\Str;
use Nexus\Database\NexusDB;

class Install
{
    protected $currentStep;

    protected $minimumPhpVersion = '8.0.2';

    protected $progressKeyPrefix = '__step';

    protected $steps = ['环境检测', '添加 .env 文件', '创建数据表', '导入数据', '创建管理员账号'];

    protected $initializeTables = [
        'adminpanel', 'agent_allowed_exception', 'agent_allowed_family', 'allowedemails', 'audiocodecs', 'bannedemails', 'categories',
        'caticons', 'codecs', 'countries', 'downloadspeed', 'faq', 'isp', 'language', 'media', 'modpanel', 'processings', 'rules', 'schools',
        'searchbox', 'secondicons', 'sources', 'standards', 'stylesheets', 'sysoppanel', 'teams', 'torrents_state', 'uploadspeed',
    ];

    protected $envNames = ['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'REDIS_HOST', 'REDIS_PORT', 'REDIS_DB'];

    public function __construct()
    {
        if (!session_id()) {
            session_start();
        }
        $this->currentStep = min(intval($_REQUEST['step'] ?? 1) ?: 1, count($this->steps) + 1);
    }

    public function listShouldInitializeTables()
    {
        return $this->initializeTables;
    }

    public function currentStep()
    {
        return $this->currentStep;
    }

    public function canAccessStep($step)
    {
        for ($i = 1; $i < $step; $i++) {
            $progressKey = $this->getProgressKey($i);
            if (!isset($_SESSION[$progressKey])) {
                $this->doLog("check step: $i, session doesn't have" );
                return false;
            }
        }
        $this->doLog("check step: $step, can access" );
        return true;
    }

    public function doneStep($step)
    {
        $progressKey = $this->getProgressKey($step);
        $this->doLog("doneStep: $step, $progressKey = 1");
        $_SESSION[$progressKey] = 1;
    }

    private function getProgressKey($step)
    {
        return $this->progressKeyPrefix . $step;
    }

    public function getLogFile()
    {
        return sprintf('%s/nexus-install-%s.log', sys_get_temp_dir(), date('Ymd'));
    }

    public function getInsallDirectory()
    {
        return ROOT_PATH . 'public/install';
    }

    public function doLog($log)
    {
        $log = sprintf('[%s] [%s] %s%s', date('Y-m-d H:i:s'), $this->currentStep, $log, PHP_EOL);
        file_put_contents($this->getLogFile(), $log, FILE_APPEND);
    }

    public function listAllTableCreate($sqlFile = '')
    {
        if (empty($sqlFile)) {
            $sqlFile = ROOT_PATH . '_db/dbstructure_v1.6.sql';
        }
        $pattern = '/CREATE TABLE `(.*)`.*;/isU';
        $string = file_get_contents($sqlFile);
        if ($string === false) {
            throw new \RuntimeException("sql file: $sqlFile can not read, make sure it exits and can be read.");
        }
        $count = preg_match_all($pattern, $string, $matches, PREG_SET_ORDER);
        if ($count == 0) {
            return [];
        }
        return array_column($matches, 0, 1);
    }

    public function listAllTableCreateFromMigrations()
    {
        $tables = [];
        foreach (glob(ROOT_PATH . "database/migrations/*.php") as $path) {
            $filename = basename($path);
            $count = preg_match('/create_(.*)_table.php/', $filename, $matches);
            if ($count) {
                $tables[$matches[1]] = $filename;
            }
        }
        return $tables;
    }

    public function listExistsTable()
    {
        $sql = 'show tables';
        $res = sql_query($sql);
        $data = [];
        while ($row = mysql_fetch_row($res)) {
            $data[] = $row[0];
        }
        return $data;
    }

    public function listRequirementTableRows()
    {
        $gdInfo = function_exists('gd_info') ? gd_info() : [];
        $extensions = ['ctype', 'fileinfo', 'json', 'mbstring', 'openssl', 'pdo_mysql', 'tokenizer', 'xml', 'mysqli', 'gd', 'bcmath'];
        $tableRows = [];
        $tableRows[] = [
            'label' => 'PHP version',
            'required' => '>= ' . $this->minimumPhpVersion,
            'current' => PHP_VERSION,
            'result' => $this->yesOrNo(version_compare(PHP_VERSION, $this->minimumPhpVersion, '>=')),
        ];
        foreach ($extensions as $extension) {
            $tableRows[] = [
                'label' => "PHP extension $extension",
                'required' => 'enabled',
                'current' => extension_loaded($extension),
                'result' => $this->yesOrNo(extension_loaded($extension)),
            ];
        }
        $tableRows[] = [
            'label' => 'PHP extension gd JPEG Support',
            'required' => 'true',
            'current' => $gdInfo['JPEG Support'] ?? '',
            'result' => $this->yesOrNo($gdInfo['JPEG Support'] ?? ''),
        ];
        $tableRows[] = [
            'label' => 'PHP extension gd PNG Support',
            'required' => 'true',
            'current' => $gdInfo['PNG Support'] ?? '',
            'result' => $this->yesOrNo($gdInfo['PNG Support'] ?? ''),
        ];
        $tableRows[] = [
            'label' => 'PHP extension gd GIF Read Support',
            'required' => 'true',
            'current' => $gdInfo['GIF Read Support'] ?? '',
            'result' => $this->yesOrNo($gdInfo['GIF Read Support'] ?? ''),
        ];
        $tableRows[] = [
            'label' => 'PHP extension redis',
            'required' => 'optional',
            'current' => extension_loaded('redis'),
            'result' => $this->yesOrNo(extension_loaded('redis')),
        ];

        $fails = array_filter($tableRows, function ($value) {return in_array($value['required'], ['true', 'enabled']) && $value['result'] == 'NO';});
        $pass = empty($fails);

        return [
            'table_rows' => $tableRows,
            'pass' => $pass,
        ];
    }

    public function listSettingTableRows()
    {
        $defaultSettingsFile = __DIR__ . '/settings.default.php';
        $originalConfigFile = ROOT_PATH . 'config/allconfig.php';
        if (!file_exists($defaultSettingsFile)) {
            throw new \RuntimeException("default setting file: $defaultSettingsFile not exists.");
        }
        if (!file_exists($originalConfigFile)) {
            throw new \RuntimeException("original setting file: $originalConfigFile not exists.");
        }
        $tableRows = [
            [
                'label' => basename($defaultSettingsFile),
                'required' => 'exists && readable',
                'current' => $defaultSettingsFile,
                'result' =>  $this->yesOrNo(file_exists($defaultSettingsFile) && is_readable($defaultSettingsFile)),
            ],
            [
                'label' => basename($originalConfigFile),
                'required' => 'exists && readable',
                'current' => $originalConfigFile,
                'result' =>  $this->yesOrNo(file_exists($originalConfigFile) && is_readable($originalConfigFile)),
            ],
        ];
        $requireDirs = [
            'main' => ['bitbucket', ],
            'attachment' => ['savedirectory', ],
        ];
        $symbolicLinks = [];
        require $originalConfigFile;
        $settings = require $defaultSettingsFile;
        $settingsFromDb = [];
        if (get_row_count('settings') > 0) {
            $settingsFromDb = get_setting();
        }
        $this->doLog("settings form db: " . json_encode($settingsFromDb));
        foreach ($settings as $prefix => &$group) {
            $prefixUpperCase = strtoupper($prefix);
            $oldGroupValues = $$prefixUpperCase ?? null;
            foreach ($group as $key => &$value) {
                //merge original config or db config to default setting, exclude code part
                if ($prefix != 'code') {
                    if (isset($settingsFromDb[$prefix][$key])) {
                        $this->doLog(sprintf(
                            "$prefix.$key, db exists, change from: %s => %s",
                            is_scalar($value) ? $value : json_encode($value),
                            is_scalar($settingsFromDb[$prefix][$key]) ? $settingsFromDb[$prefix][$key] : json_encode($settingsFromDb[$prefix][$key]))
                        );
                        $value = $settingsFromDb[$prefix][$key];
                    } elseif (isset($oldGroupValues) && isset($oldGroupValues[$key])) {
                        $this->doLog(sprintf(
                            "$prefix.$key, original config file exists, change from: %s => %s",
                            is_scalar($value) ? $value : json_encode($value),
                            is_scalar($oldGroupValues[$key]) ? $oldGroupValues[$key] : json_encode($oldGroupValues[$key]))
                        );
                        $value = $oldGroupValues[$key];
                    }
                }
                if ($prefix == 'basic' && Str::startsWith($value, 'localhost')) {
                    $value = '';
                }
                if (isset($requireDirs[$prefix]) && in_array($key, $requireDirs[$prefix])) {
                    $dir = getFullDirectory($value);
                    $tableRows[] = [
                        'label' => "{$prefix}.{$key}",
                        'required' => 'exists && readable',
                        'current' => $dir,
                        'result' => $this->yesOrNo(is_dir($dir) && is_readable($dir)),
                    ];
                    $symbolicLinks[] = $dir;
                }
            }
        }
        $fails = array_filter($tableRows, function ($value) {return $value['required'] == 'true' && $value['result'] == 'NO';});
        $pass = empty($fails);
        return [
            'table_rows' => $tableRows,
            'symbolic_links' => $symbolicLinks,
            'settings' => $settings,
            'pass' => $pass,
        ];
    }

    public function nextStep()
    {
        $this->doneStep($this->currentStep);
        $this->gotoStep($this->currentStep + 1);
    }

    public function gotoStep($step)
    {
        nexus_redirect(getBaseUrl() . "?step=$step");
        die(0);
    }

    public function maxStep()
    {
        return count($this->steps);
    }

    public function yesOrNo($condition) {
        if ($condition) {
            return 'YES';
        }
        return 'NO';
    }

    public function renderTable($header, $data)
    {
        $table = '<div class="table w-full text-left">';
        $table .= '<div class="table-row-group">';
        $table .= '<div class="table-row">';
        foreach ($header as $text) {
            $table .= '<div class="table-cell bg-gray-400 text-gray-700 px-4 py-2">' . $text . '</div>';
        }
        $table .= '</div>';
        foreach ($data as $value) {
            $table .= '<div class="table-row">';
            foreach ($header as $name => $text) {
                $color = 'gray';
                if ($name == 'result' && in_array($value[$name], ['YES', 'NO'])) {
                    $color = $value[$name] == 'YES' ? 'green' : 'red';
                }
                $table .= '<div class="table-cell bg-gray-200 text-' . $color . '-700 px-4 py-2 text-sm">' . $value[$name] . '</div>';
            }
            $table .= '</div>';
        }
        $table .= '</div>';
        $table .= '</div>';

        return $table;

    }

    public function renderForm($formControls, $formWidth = '1/2', $labelWidth = '1/3', $valueWidth = '2/3')
    {
        $form = '<div class="inline-block w-' . $formWidth . '">';
        foreach ($formControls as $value) {
            $form .= '<div class="flex mt-2">';
            $form .= sprintf('<div class="w-%s flex justify-end items-center pr-10"><span>%s</span></div>', $labelWidth, $value['label']);
            $form .= sprintf(
                '<div class="w-%s flex justify-start items-center pr-10"><input class="border py-2 px-3 text-grey-darkest w-full" type="text" name="%s" value="%s" /></div>',
                $valueWidth, $value['name'], $value['value'] ?? ''
            );
            $form .= '</div>';
        }
        $form .= '</div>';
        return $form;
    }

    public function renderSteps()
    {
        $steps = '<div class="flex mt-10 step text-center">';
        $currentStep = $this->currentStep();
        foreach ($this->steps as $key => $value) {
            $steps .= sprintf('<div class="flex-1 %s">', $currentStep > $key + 1 ? 'text-green-500' : ($currentStep < $key + 1 ? 'text-gray-500' : ''));
            $steps .= sprintf('<div>第%s步</div>', $key + 1);
            $steps .= sprintf('<div>%s</div>', $value);
            $steps .= '</div>';
        }
        $steps .= '</div>';
        return $steps;
    }

    public function listEnvFormControls()
    {
        $envExampleFile = ROOT_PATH . ".env.example";
        $envExampleData = readEnvFile($envExampleFile);
        $envFile = ROOT_PATH . '.env';
        $envData = [];
        if (file_exists($envFile) && is_readable($envFile)) {
            //already exists, read it ,and merge
            $envData = readEnvFile($envFile);
        }
        $mergeData = array_merge($envExampleData, $envData);
        $formControls = [];
        foreach ($this->envNames as $name) {
            $value = $mergeData[$name];
            if (isset($_POST[$name])) {
                $value = $_POST[$name];
            }
            $formControls[] = [
                'label' => $name,
                'name' => $name,
                'value' => $value,
            ];
        }

        return $formControls;
    }

    public function createAdministrator($username, $email, $password, $confirmPassword)
    {
        $count = get_row_count('users', 'where class = 16');
        if ($count > 0) {
            throw new \InvalidArgumentException("Administrator already exists");
        }
        if (!validusername($username)) {
            throw new \InvalidArgumentException("Innvalid username: $username");
        }
        $email = htmlspecialchars(trim($email));
        $email = safe_email($email);
        if (!check_email($email)) {
            throw new \InvalidArgumentException("Innvalid email: $email");
        }
        $res = sql_query("SELECT id FROM users WHERE email=" . sqlesc($email));
        $arr = mysql_fetch_row($res);
        if ($arr) {
            throw new \InvalidArgumentException("The email address: $email is already in use");
        }
        if (mb_strlen($password) < 6 || mb_strlen($password) > 40) {
            throw new \InvalidArgumentException("Innvalid password: $password, it should be more than 6 character and less than 40 character");
        }
        if ($password != $confirmPassword) {
            throw new \InvalidArgumentException("confirmPassword: $confirmPassword != password");
        }
        $setting = get_setting('main');
        $secret = mksecret();
        $passhash = md5($secret . $password . $secret);
        $insert = [
            'username' => $username,
            'passhash' => $passhash,
            'secret' => $secret,
            'email' => $email,
            'stylesheet' => $setting['defstylesheet'],
            'class' => 16,
            'status' => 'confirmed',
            'added' => date('Y-m-d H:i:s'),
        ];
        $this->doLog("[CREATE ADMINISTRATOR] " . json_encode($insert));
        return NexusDB::insert('users', $insert);
    }

    public function createEnvFile($data)
    {
        $envExampleFile = ROOT_PATH . ".env.example";
        $envExampleData = readEnvFile($envExampleFile);
        $envFile = ROOT_PATH . ".env";
        $newData = [];
        if (file_exists($envFile) && is_readable($envFile)) {
            //already exists, read it ,and merge post data
            $newData = readEnvFile($envFile);
            $this->doLog("[CREATE ENV] .env exists, data: " . json_encode($newData));
        }
        $this->doLog("[CREATE ENV] newData: " . json_encode($newData));
        foreach ($envExampleData as $key => $value) {
            if (isset($data[$key])) {
                $value = trim($data[$key]);
                $this->doLog("[CREATE ENV] key: $key, new value: $value from post.");
                $newData[$key] = $value;
            } elseif (!isset($newData[$key])) {
                $this->doLog("[CREATE ENV] key: $key, new value: $value from example.");
                $newData[$key] = $value;
            }
        }
        $this->doLog("[CREATE ENV] final newData: " . json_encode($newData));
        unset($key, $value);
        mysql_connect($newData['DB_HOST'], $newData['DB_USERNAME'], $newData['DB_PASSWORD'], $newData['DB_DATABASE'], $newData['DB_PORT']);
        if (extension_loaded('redis') && !empty($newData['REDIS_HOST'])) {
            $redis = new \Redis();
            $redis->connect($newData['REDIS_HOST'], $newData['REDIS_PORT'] ?: 6379);
            if (isset($newData['REDIS_DB'])) {
                if (!ctype_digit($newData['REDIS_DB']) || $newData['REDIS_DB'] < 0 || $newData['REDIS_DB'] > 15) {
                    throw new \InvalidArgumentException("invalid redis database: " . $newData['REDIS_DB']);
                }
                $redis->select($newData['REDIS_DB']);
            }
        }
        $content = "";
        foreach ($newData as $key => $value) {
            $content .= "{$key}={$value}\n";
        }
        $fp = @fopen($envFile, 'w');
        if ($fp === false) {
            throw new \RuntimeException("can't create env file, make sure php has permission to create file at: " . ROOT_PATH);
        }
        fwrite($fp, $content);
        fclose($fp);
        $this->doLog("[CREATE ENV] $envFile with content: $content");
        return true;
    }

    public function listShouldCreateTable()
    {
        $existsTable = $this->listExistsTable();
//        $tableCreate = $this->listAllTableCreate();
        $tableCreate = $this->listAllTableCreateFromMigrations();
        $shouldCreateTable = [];
        foreach ($tableCreate as $table => $sql) {
            if (in_array($table, $existsTable)) {
                continue;
            }
            $shouldCreateTable[$table] = $sql;
        }
        return $shouldCreateTable;
    }

    public function createTable(array $createTable)
    {
        foreach ($createTable as $table => $sql) {
            $this->doLog("[CREATE TABLE] $table \n $sql");
            sql_query($sql);
        }
        return true;
    }

    public function saveSettings($settings)
    {
        foreach ($settings as $prefix => $group) {
            $this->doLog("[SAVE SETTING], prefix: $prefix, nameAndValues: " . json_encode($group));
            saveSetting($prefix, $group);
        }
    }

    public function createSymbolicLinks($symbolicLinks)
    {
        foreach ($symbolicLinks as $path) {
            $linkName = ROOT_PATH . 'public/' . basename($path);
            if (is_link($linkName)) {
                $this->doLog("path: $linkName already exits, skip create symbolic link $linkName -> $path");
                continue;
            }
            $linkResult = symlink($path, $linkName);
            if ($linkResult === false) {
                throw new \RuntimeException("can not make symbolic link:  $linkName -> $path");
            }
            $this->doLog("[CREATE SYMBOLIC LINK] success make symbolic link: $linkName -> $path");
        }
        return true;
    }

    public function importInitialData($sqlFile = '')
    {
        if (empty($sqlFile)) {
            $sqlFile = ROOT_PATH . '_db/dbstructure_v1.6.sql';
        }
        $string = file_get_contents($sqlFile);
        if ($string === false) {
            throw new \RuntimeException("can't not read dbstructure file: $sqlFile");
        }
        //@todo test in php 7.3
        $pattern = "/INSERT INTO `(\w+)` VALUES \(.*\);/i";
        preg_match_all($pattern, $string, $matches, PREG_SET_ORDER);
        $this->doLog("[IMPORT DATA] matches count: " . count($matches));
        foreach ($matches as $match) {
            $table = $match[1];
            $sql = trim($match[0]);
            if (!in_array($table, $this->initializeTables)) {
                continue;
            }
            //if table not empty, skip
            $count = get_row_count($table);
            if ($count > 0) {
                $this->doLog("[IMPORT DATA] $table, not empty, skip");
                continue;
            }
            $this->doLog("[IMPORT DATA] $table, $sql");
            sql_query("truncate table $table");
            sql_query($sql);
        }
        return true;
    }

    public function runMigrate($path = null)
    {
        if (!WITH_LARAVEL) {
            throw new \RuntimeException('Laravel is not available.');
        }
        $command = "php " . ROOT_PATH . "artisan migrate";
        if (!is_null($path)) {
            $command .= " --path=$path";
        }
        $command .= " --force";
        $this->executeCommand($command);
        $this->doLog("[MIGRATE] success.");
    }

    public function executeCommand($command)
    {
        $this->doLog("command: $command");
        $result = exec($command, $output, $result_code);
        $this->doLog(sprintf('result_code: %s, result: %s', $result_code, $result));
        $this->doLog("output: " . json_encode($output));
        if ($result_code != 0) {
            throw new \RuntimeException(json_encode($output));
        }
    }

    public function runDatabaseSeeder()
    {
        if (!WITH_LARAVEL) {
            throw new \RuntimeException('Laravel is not available.');
        }
        $command = "php " . ROOT_PATH . "artisan db:seed";
        $result = exec($command, $output, $result_code);
        $this->doLog(sprintf('command: %s, result_code: %s, result: %s', $command, $result_code, $result));
        $this->doLog("output: " . json_encode($output));
        if ($result_code != 0) {
            throw new \RuntimeException(json_encode($output));
        } else {
            $this->doLog("[DATABASE_SEED] success.");
        }
    }
}
