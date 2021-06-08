<?php
$rootpath = dirname(dirname(__DIR__)) . '/';
define('ROOT_PATH', $rootpath);
require ROOT_PATH . 'nexus/Install/install_update_start.php';

$isPost = $_SERVER['REQUEST_METHOD'] == 'POST';
$update = new \Nexus\Install\Update();
$currentStep = $update->currentStep();
$maxStep = $update->maxStep();
if (!$update->canAccessStep($currentStep)) {
    $update->gotoStep(1);
}
$error = $copy = '';
$pass = true;

//step 1
if ($currentStep == 1) {
    $requirements = $update->listRequirementTableRows();
    $pass = $requirements['pass'];
    if ($isPost) {
        $update->nextStep();
    }
}

if ($currentStep == 2) {
    $envExampleFile = $rootpath . ".env.example";
//    $dbstructureFile = $rootpath . "_db/dbstructure_v1.6.sql";
    $envExampleData = readEnvFile($envExampleFile);
    $envFormControls = $update->listEnvFormControls();
    $newData = array_column($envFormControls, 'value', 'name');
    while ($isPost) {
        try {
            $update->createEnvFile($_POST);
            $update->nextStep();
        } catch (\Exception $exception) {
            $error = $exception->getMessage();
            break;
        }
        break;
    }
    $tableRows = [
        [
            'label' => basename($envExampleFile),
            'required' => 'exists && readable',
            'current' => $envExampleFile,
            'result' =>  $update->yesOrNo(file_exists($envExampleFile) && is_readable($envExampleFile)),
        ],
//        [
//            'label' => basename($dbstructureFile),
//            'required' => 'exists && readable',
//            'current' => $dbstructureFile,
//            'result' =>  $update->yesOrNo(file_exists($dbstructureFile) && is_readable($dbstructureFile)),
//        ],
    ];
    $fails = array_filter($tableRows, function ($value) {return $value['result'] == 'NO';});
    $pass = empty($fails);
}

if ($currentStep == 3) {
//    $createTables = $update->listAllTableCreate();
    $createTables = $update->listAllTableCreateFromMigrations();
    $existsTables = $update->listExistsTable();
    $tableRows = [];
    $toCreateTable = $toAlterTable = $toUpdateTable = [];
    foreach ($createTables as $table => $tableCreate) {
        //Table not exists
        if (!in_array($table, $existsTables)) {
            $tableRows[] = [
                "label" => "Table: $table",
                "required" => "exists",
                "current" => "",
                "result" => 'NO',
            ];
            $toCreateTable[$table] = $tableCreate;
            continue;
        }
        $tableHaveFields = $update->listTableFieldsFromDb($table);
        foreach ($tableHaveFields as $field => $fieldInfo) {
            if ($fieldInfo['Type'] == 'datetime' && $fieldInfo['Default'] == '0000-00-00 00:00:00') {
                $tableRows[] = [
                    'label' => "Field: $table.$field",
                    'required' => 'default null',
                    'current' => '0000-00-00 00:00:00',
                    'result' => 'NO',
                ];
                $toAlterTable[$table][$field] = "modify $field datetime default null";
                $toUpdateTable[$table][$field] = "null";
            }
        }
    }
    while ($isPost) {
        try {
            sql_query('SET sql_mode=(SELECT REPLACE(@@sql_mode,"NO_ZERO_DATE", ""))');
            $command = "php " . ROOT_PATH . "artisan install:migrate";
            $result = exec($command, $output, $result_code);
            $update->doLog(sprintf('command: %s, result_code: %s, result: %s', $command, $result_code, $result));
            $update->doLog("output: " . json_encode($output));
            if ($result_code != 0) {
                throw new \RuntimeException(json_encode($output));
            } else {
                $update->doLog("[MIGRATE] success.");
            }
            foreach ($toAlterTable as $table => $modies) {
                $query = "alter table $table " . implode(', ', $modies);
                $update->doLog("[ALTER TABLE] $query");
                sql_query($query);
            }
            foreach ($toUpdateTable as $table => $updates) {
                foreach ($updates as $field => $fieldUpdate) {
                    $query = sprintf("update %s set %s = %s where %s = '0000-00-00 00:00:00'", $table, $field, $fieldUpdate, $field);
                    $update->doLog("[UPDATE TABLE] $query, affectedRows: " . mysql_affected_rows());
                    sql_query($query);
                }
            }

            $update->runExtraQueries();

            $update->nextStep();
        } catch (\Exception $exception) {
            $update->doLog($exception->getMessage() . $exception->getTraceAsString());
            $error = $exception->getMessage();
            break;
        }
        break;
    }
}

if ($currentStep == 4) {
    $settingTableRows = $update->listSettingTableRows();
    $settings = $settingTableRows['settings'];
    $symbolicLinks = $settingTableRows['symbolic_links'];
    $tableRows = $settingTableRows['table_rows'];
    $pass = $settingTableRows['pass'];
    while ($isPost) {
        try {
            $update->createSymbolicLinks($symbolicLinks);
            $update->saveSettings($settings);
            $update->nextStep();
        } catch (\Exception $e) {
            $error = $e->getMessage();
            break;
        }
        break;
    }
}

if (!empty($error)) {
    $pass = false;
}
?>

<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
    <title>Update NexusPHP | step <?php echo $currentStep?></title>
</head>
<body>
<div class="container mx-auto">
    <?php echo $update->renderSteps()?>
    <div class="mt-10">
        <form method="post" action="<?php echo getBaseUrl() . '?step=' . $currentStep?>">
            <input type="hidden" name="step" value="<?php echo $currentStep?>">
            <?php
            echo'<div class="step-' . $currentStep . ' text-center">';
            $header = ['项目', '要求', '当前', '结果'];
            if ($currentStep == 1) {
                echo $update->renderTable($header, $requirements['table_rows']);
            } elseif ($currentStep == 2) {
                echo $update->renderTable($header, $tableRows);
                echo '<div class="text-gray-700 p-4 text-red-400">若 Redis 不启用，相关项目留空</div>';
                echo $update->renderForm($envFormControls);

            } elseif ($currentStep == 3) {
                echo '<h1 class="mb-4 text-lg font-bold">需要修改或创建以下数据表(字段)</h1>';
                if (empty($tableRows)) {
                    echo '<div class="text-green-600 text-center">恭喜，需要的表(字段)均符合要求!</div>';
                } else {
                    echo $update->renderTable($header, $tableRows);
                }
            } elseif ($currentStep == 4) {
                echo $update->renderTable($header, $tableRows);
                echo '<div class="text-blue-500 pt-10">';
                echo sprintf('这一步会把 <code>%s</code> 的数据合并到 <code>%s</code>, 然后插入数据库中。', $tableRows[1]['label'], $tableRows[0]['label']);
                echo '</div>';
            } elseif ($currentStep > $maxStep) {
                echo '<div class="text-green-900 text-6xl p-10">恭喜，一切就绪！</div>';
                echo '<div class="mb-6">有问题可查阅升级日志：<code>' . $update->getLogFile() . '</code></div>';
                echo '<div class="text-red-500">为安全起见，请删除以下目录</div>';
                echo '<div class="text-red-500"><code>' . $update->getUpdateDirectory() . '</code></div>';
            }
            echo'</div>';

            if (!empty($error)) {
                echo sprintf('<div class="text-center text-red-500 p-4">Error: %s</div>', nl2br($error));
                unset($error);
            }
            if (!empty($copy)) {
                echo sprintf('<div class="text-center"><textarea class="w-1/2 h-40 border">%s</textarea></div>', $copy);
                unset($copy);
            }
            ?>
            <div class="mt-10 text-center">
                <button class="bg-blue-500 p-2 m-4 text-white rounded" type="button" onclick="goBack()">上一步</button>
                <?php if ($currentStep <= $maxStep) {?>
                    <button class="bg-blue-<?php echo $pass ? 500 : 200;?> p-2 m-4 text-white rounded" type="submit" <?php echo $pass ? '' : 'disabled';?>>下一步</button>
                <?php } else {?>
                    <a class="bg-blue-500 p-2 m-4 text-white rounded" href="<?php echo getSchemeAndHttpHost()?>">回首页</a>
                <?php }?>
            </div>
        </form>
    </div>
</div>
<div class="m-10 text-center">
    欢迎使用 NexusPHP 升级程序，如有疑问，点击<a href="https://nexusphp.org/" target="_blank" class="text-blue-500 p-1">这里</a>获取帮助。
</div>
</body>
<script>
    function goBack() {
        window.location.search="step=<?php echo $currentStep == 1 ? 1 : $currentStep - 1?>"
    }
</script>
</html>
