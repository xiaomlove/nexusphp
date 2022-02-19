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
    $tableRows = [];
    $versionTable = $versions = [];
    $cacheKkey = '__versions_' . date('Ymd_H');
    try {
        $versions = $update->listVersions();
//        if (!empty($_SESSION[$cacheKkey])) {
//            $update->doLog("get versions from session.");
//            $versions = $_SESSION[$cacheKkey];
//        } else {
//            $_SESSION[$cacheKkey] = $versions;
//        }
    } catch (\Exception $exception) {
        $error = $exception->getMessage();
    }
    if (!$isPost) {
        $versionHeader = [
            'checkbox' => '选择',
            'tag_name' => '版本(标签)',
            'name' => '名称',
            'published_at' => '发布时间',
        ];
        $tableRows[] = [
            'checkbox' => sprintf('<input type="radio" name="version_url" value="manual"/>'),
            'tag_name' => '手动更新',
            'name' => '如若有改动不宜全量覆盖，请勾选此选项并确保已经手动更新了代码',
            'published_at' => '---',
        ];
        $latestCommit = $update->getLatestCommit();
        $time = \Carbon\Carbon::parse($latestCommit['committer']['date']);
        $time->tz = nexus_env('TIMEZONE');
        $tableRows[] = [
            'checkbox' => sprintf('<input type="radio" name="version_url" value="development|%s"/>', $latestCommit['sha']),
            'tag_name' => '最新开发代码',
            'name' => "仅限开发测试！最新提交：" . $latestCommit['commit']['message'],
            'published_at' => $time->format('Y-m-d H:i:s'),
        ];
        foreach ($versions as $version) {
            if ($version['draft']) {
                continue;
            }
            $time = \Carbon\Carbon::parse($version['published_at']);
            $time->tz = nexus_env('TIMEZONE');
            $versionUrl = $version['tag_name'] . '|' . $version['tarball_url'];
            $checked = !empty($_REQUEST['version_url']) && $_REQUEST['version_url'] == $versionUrl ? ' checked' : '';
            $tableRows[] = [
                'checkbox' => sprintf('<input type="radio" name="version_url" value="%s"%s/>', $versionUrl, $checked),
                'tag_name' => $version['tag_name'],
                'name' => $version['name'],
                'published_at' => $time->format('Y-m-d H:i:s'),
            ];
        }
    }

//    dd($tableRows);
    while ($isPost) {
        try {
            if (empty($_REQUEST['version_url'])) {
                throw new \RuntimeException("没有选择版本");
            }
            $downloadUrl = '';
            if ($_REQUEST['version_url'] == 'manual') {
                $update->nextStep();
            } elseif (\Illuminate\Support\Str::startsWith($_REQUEST['version_url'], 'development')) {
                $downloadUrlArr = explode('|', $_REQUEST['version_url']);
                $downloadUrl = sprintf('https://github.com/xiaomlove/nexusphp/archive/%s.zip', $downloadUrlArr[1]);
            } else {
                $versionUrlArr = explode('|', $_REQUEST['version_url']);
                $version = strtolower($versionUrlArr[0]);
                $downloadUrl = $versionUrlArr[1];
                if (\Illuminate\Support\Str::startsWith($version, 'v')) {
                    $version = substr($version, 1);
                }
                $update->doLog("version: $version, downloadUrl: $downloadUrl, currentVersion: " . VERSION_NUMBER);
                if (version_compare($version, VERSION_NUMBER, '<=')) {
                    throw new \RuntimeException("必须选择一个高于当前版本（" . VERSION_NUMBER . "）的");
                }
            }
            $update->downAndExtractCode($downloadUrl);
            $update->nextStep();
        } catch (\Exception $exception) {
            $update->doLog($exception->getMessage() . $exception->getTraceAsString());
            $error = $exception->getMessage();
            break;
        }
        break;
    }
}

if ($currentStep == 3) {
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


if ($currentStep == 4) {
    $settingTableRows = $update->listSettingTableRows();
    $settings = $settingTableRows['settings'];
    $symbolicLinks = $settingTableRows['symbolic_links'];
    $tableRows = $settingTableRows['table_rows'];
    $pass = $settingTableRows['pass'];
    while ($isPost) {
        try {
//            $update->updateDependencies();
            $update->createSymbolicLinks($symbolicLinks);
            $update->saveSettings($settings);
            $update->runExtraQueries();
            $update->runMigrate();
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
            $header = [
                'label' => '项目',
                'required' => '要求',
                'current'=> '当前',
                'result'=> '结果'
            ];
            if ($currentStep == 1) {
                echo $update->renderTable($header, $requirements['table_rows']);
            } elseif ($currentStep == 3) {
                echo $update->renderTable($header, $tableRows);
                echo '<div class="text-gray-700 p-4 text-red-400">下一步之前，请确保在根目录执行了 composer install 更新了依赖。</div>';
                echo $update->renderForm($envFormControls);

            } elseif ($currentStep == 2) {
                if (empty($tableRows)) {
                    echo '<div class="text-green-600 text-center">抱歉，暂无任何版可以选择！</div>';
                } else {
                    echo $update->renderTable($versionHeader, $tableRows);
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
