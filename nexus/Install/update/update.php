<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 0);
$rootpath = dirname(dirname(__DIR__)) . '/';
define('ROOT_PATH', $rootpath);
$isPost = $_SERVER['REQUEST_METHOD'] == 'POST';
require $rootpath . 'vendor/autoload.php';
require $rootpath . 'include/globalfunctions.php';
require $rootpath . 'include/functions.php';
require $rootpath . 'nexus/Database/helpers.php';

$update = new \Nexus\Install\Update();
$currentStep = $update->currentStep();
$maxStep = $update->maxStep();
if (!$update->canAccessStep($currentStep)) {
    $update->gotoStep(1);
}
$error = $copy = '';

//step 1
if ($currentStep == 1) {
    $requirements = $update->listRequirementTableRows();
    $pass = $requirements['pass'];
    if ($isPost) {
        $update->nextStep();
    }
}

if ($currentStep == 2) {
    $envExampleFile = "$rootpath.env.example";
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
            'label' => '.env.example',
            'required' => 'exists && readable',
            'current' => $envExampleFile,
            'result' =>  $update->yesOrNo(file_exists($envExampleFile) && is_readable($envExampleFile)),
        ],
    ];
    $fails = array_filter($tableRows, function ($value) {return $value['result'] == 'NO';});
    $pass = empty($fails);
}

if ($currentStep == 3) {
    $pass = true;
    $shouldCreateTable = $update->listShouldCreateTable();
    while ($isPost) {
        try {
            $update->createTable($shouldCreateTable);
            $update->nextStep();
        } catch (\Exception $exception) {
            $error = $exception->getMessage();
            break;
        }
        break;
    }
}

//if ($currentStep == 4) {
//    $pass = true;
//    while (true) {
//        $shouldAlterTable = listShouldAlterTable();
//        if ($isPost) {
//            if (!empty($shouldAlterTable)) {
//                try {
//                    sql_query('SET sql_mode=(SELECT REPLACE(@@sql_mode,"NO_ZERO_DATE", ""));');
//                    foreach ($shouldAlterTable as $table => $fields) {
//                        $sqlAlter = "alter table $table";
//                        $sqlUpdate = "update $table";
//                        $updateWhere = [];
//                        foreach ($fields as $field) {
//                            $sqlAlter .= " modify $field datetime default null,";
//                            $sqlUpdate .= " set $field = null,";
//                            $updateWhere[] = "$field = '0000-00-00 00:00:00'";
//                        }
//                        $sqlAlter = rtrim($sqlAlter, ',');
//                        $sqlUpdate = rtrim($sqlUpdate, ',') . " where " . implode(' or ', $updateWhere);
//                        sql_query($sqlUpdate);
//                        sql_query($sqlAlter);
//                    }
//                } catch (\Exception $e) {
//                    $error = $e->getMessage();
//                    break;
//                }
//            }
//            goStep($currentStep + 1);
//        }
//        break;
//    }
//}

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
            $update->importInitialData();
            $update->nextStep();
        } catch (\Exception $e) {
            $error = $e->getMessage();
            break;
        }
        break;
    }
}

if ($currentStep == 5) {
    if ($isPost) {
        try {
            $update->createAdministrator($_POST['username'], $_POST['email'], $_POST['password'], $_POST['confirm_password']);
            $update->nextStep();
        } catch (\Exception $exception) {
            $error = $exception->getMessage();
        }
    }
    $pass = true;
    $userFormControls = [
        ['label' => '用户名', 'name' => 'username', 'value' => $_POST['username'] ?? ''],
        ['label' => '邮箱', 'name' => 'email', 'value' => $_POST['email'] ?? ''],
        ['label' => '密码', 'name' => 'password', 'value' => $_POST['password'] ?? ''],
        ['label' => '确认密码', 'name' => 'confirm_password', 'value' => $_POST['confirm_password'] ?? ''],
    ];
}
?>

<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
    <title>Install NexusPHP | step <?php echo $currentStep?></title>
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
                echo '<h1 class="mb-4 text-lg font-bold">需要新建以下数据表</h1>';
                if (empty($shouldCreateTable)) {
                    echo '<div class="text-green-600 text-center">恭喜，需要的表均已创建!</div>';
                } else {
                    echo sprintf('<div class="h-64 text-left inline-block w-2/3"><code class="bolck w-px-100">%s</code></div>', implode(', ', array_keys($shouldCreateTable)));
                }
            } elseif ($currentStep == 4) {
                echo $update->renderTable($header, $tableRows);
                echo '<div class="text-blue-500 pt-10">';
                echo sprintf('这一步会把 <code>%s</code> 的数据合并到 <code>%s</code>, 然后插入数据库中。', $tableRows[1]['label'], $tableRows[0]['label']);
                echo '</div>';
            } elseif ($currentStep == 5) {
                echo $update->renderForm($userFormControls, '1/3', '1/4', '3/4');
            } elseif ($currentStep > $maxStep) {
                echo '<div class="text-green-900 text-6xl p-10">恭喜，一切就绪！</div>';
                echo '<div class="mb-6">有问题可查阅安装日志：<code>' . $update->getLogFile() . '</code></div>';
                echo '<div class="text-red-500">为安全起见，请删除以下目录</div>';
                echo '<div class="text-red-500"><code>' . $update->getInsallDirectory() . '</code></div>';
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
                    <a class="bg-blue-500 p-2 m-4 text-white rounded" href="<?php echo getSchemaAndHttpHost()?>">回首页</a>
                <?php }?>
            </div>
        </form>
    </div>
</div>
<div class="mt-10 text-center">
    欢迎使用 NexusPHP 升级程序(v1.5 ~ v1.6)，如有疑问，点击<a href="http://nexusphp.org/" target="_blank" class="text-blue-500 p-1">这里</a>获取帮助。
</div>
</body>
<script>
    function goBack() {
        window.location.search="step=<?php echo $currentStep == 1 ? 1 : $currentStep - 1?>"
    }
</script>
</html>