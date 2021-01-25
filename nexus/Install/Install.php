<?php

namespace Nexus\Install;

class Install
{
    private $currentStep;

    private $logFile;

    private $minimumPhpVersion = '7.2.0';

    protected $steps = ['环境检测', '添加 .env 文件', '新建数据表', '导入数据', '创建管理员账号'];


    public function __construct()
    {
        $this->currentStep = min($_REQUEST['step'] ?? 1, count($this->steps));
        $this->logFile = $this->getLogFile();
    }

    protected function getLogFile()
    {
        return sprintf('%s/nexus_install_%s.log', sys_get_temp_dir(), date('Ymd'));
    }

    public function doLog($log)
    {
        $log = sprintf('[%s] [%s] %s%s', date('Y-m-d H:i:s'), $this->currentStep, $log, PHP_EOL);
        file_put_contents($this->logFile, $log, FILE_APPEND);
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

    public function listExistsTable()
    {
        dbconn(false, false);
        $sql = 'show tables';
        $res = sql_query($sql);
        $data = [];
        while ($row = mysql_fetch_row($res)) {
            $data[] = $row[0];
        }
        return $data;
    }

    public function listShouldAlterTable()
    {
        $tables = $this->listExistsTable();
        $data = [];
        foreach ($tables as $table) {
            $sql = "desc $table";
            $res = sql_query($sql);
            while ($row = mysql_fetch_assoc($res)) {
                if ($row['Type'] == 'datetime' && $row['Default'] == '0000-00-00 00:00:00') {
                    $data[$table][] = $row['Field'];
                }
            }
        }
        return $data;
    }

    public function listRequirementTableRows()
    {
        $gdInfo = gd_info();
        $tableRows = [
            [
                'label' => 'PHP version',
                'required' => '>= ' . $this->minimumPhpVersion,
                'current' => PHP_VERSION,
                'result' => $this->yesOrNo(version_compare(PHP_VERSION, $this->minimumPhpVersion, '>=')),
            ],
            [
                'label' => 'PHP extension redis',
                'required' => 'optional',
                'current' => extension_loaded('redis'),
                'result' => $this->yesOrNo(extension_loaded('redis')),
            ],
            [
                'label' => 'PHP extension mysqli',
                'required' => 'enabled',
                'current' => extension_loaded('mysqli'),
                'result' => $this->yesOrNo(extension_loaded('mysqli')),
            ],
            [
                'label' => 'PHP extension mbstring',
                'required' => 'enabled',
                'current' => extension_loaded('mbstring'),
                'result' => $this->yesOrNo(extension_loaded('mbstring')),
            ],
            [
                'label' => 'PHP extension gd',
                'required' => 'enabled',
                'current' => extension_loaded('gd'),
                'result' => $this->yesOrNo(extension_loaded('gd')),
            ],
            [
                'label' => 'PHP extension gd JPEG Support',
                'required' => 'true',
                'current' => $gdInfo['JPEG Support'],
                'result' => $this->yesOrNo($gdInfo['JPEG Support']),
            ],
            [
                'label' => 'PHP extension gd PNG Support',
                'required' => 'true',
                'current' => $gdInfo['PNG Support'],
                'result' => $this->yesOrNo($gdInfo['PNG Support']),
            ],
            [
                'label' => 'PHP extension gd GIF Read Support',
                'required' => 'true',
                'current' => $gdInfo['GIF Read Support'],
                'result' => $this->yesOrNo($gdInfo['GIF Read Support']),
            ],
        ];
        $fails = array_filter($tableRows, function ($value) {return $value['required'] == 'true' && $value['result'] == 'NO';});
        $pass = empty($fails);

        return [
            'table_rows' => $tableRows,
            'pass' => $pass,
        ];
    }

    public function nextStep()
    {
        return $this->gotoStep($this->currentStep + 1);
    }

    public function gotoStep($step)
    {
        header('Location: ' . getBaseUrl() . "?step=$step");
        exit(0);
    }

    private function yesOrNo($condition) {
        if ($condition) {
            return 'YES';
        }
        return 'NO';
    }

    public function getEnvExampleData($envFile = '')
    {
        if (empty($envFile)) {
            $envFile = ROOT_PATH . '.env.example';
        }
        return readEnvFile($envFile);

    }

    public function renderTable($header, $data)
    {
        $table = '<div class="table w-full text-left">';
        $table .= '<div class="table-row-group">';
        $table .= '<div class="table-row">';
        foreach ($header as $value) {
            $table .= '<div class="table-cell bg-gray-400 text-gray-700 px-4 py-2">' . $value . '</div>';
        }
        $table .= '</div>';
        foreach ($data as $value) {
            $table .= '<div class="table-row">';
            $table .= '<div class="table-cell bg-gray-200 text-gray-700 px-4 py-2 text-sm">' . $value['label'] . '</div>';
            $table .= '<div class="table-cell bg-gray-200 text-gray-700 px-4 py-2 text-sm">' . $value['required'] . '</div>';
            $table .= '<div class="table-cell bg-gray-200 text-gray-700 px-4 py-2 text-sm">' . $value['current'] . '</div>';
            $table .= '<div class="table-cell bg-gray-200 text-gray-700 px-4 py-2 text-sm">' . $value['result'] . '</div>';
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



}