<?php

namespace Nexus\Install;

class Update extends Install
{

    protected $steps = ['环境检测', '添加 .env 文件', '修改&创建数据表', '导入数据'];

    protected $initializeTables = [
        'adminpanel', 'agent_allowed_exception', 'agent_allowed_family', 'allowedemails', 'audiocodecs', 'avps', 'bannedemails', 'categories',
        'caticons', 'codecs', 'countries', 'downloadspeed', 'faq', 'isp', 'language', 'media', 'modpanel', 'processings', 'rules', 'schools',
        'searchbox', 'secondicons', 'sources', 'standards', 'stylesheets', 'sysoppanel', 'teams', 'torrents_state', 'uploadspeed', 'agent_allowed_family',
    ];


    public function getLogFile()
    {
        return sprintf('%s/nexus_update_%s.log', sys_get_temp_dir(), date('Ymd'));
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

    public function importInitialData($sqlFile = '')
    {
        if (empty($sqlFile)) {
            $sqlFile = ROOT_PATH . '_db/dbstructure_v1.6.sql';
        }
        $string = file_get_contents($sqlFile);
        $pattern = "/INSERT INTO `(\w+)` VALUES \(.*\);\n/i";
        preg_match_all($pattern, $string, $matches, PREG_SET_ORDER);
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
}