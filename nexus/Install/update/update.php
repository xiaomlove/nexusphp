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
    $tableRows[] = [
        'checkbox' => sprintf('<input type="radio" name="version_url" value="manual"/>'),
        'tag_name' => 'Manual',
        'name' => 'If there are changes that are not suitable for full coverage, please check this box and make sure you have updated the code manually',
        'published_at' => '---',
    ];
    try {
        //Unnecessary
//        $versions = $update->listVersions();
        $versions = [];
    } catch (\Exception $exception) {
        $update->doLog("can not fetch versions from github: " . $exception->getMessage());
    }
    if (!$isPost) {
        $versionHeader = [
            'checkbox' => 'Check',
            'tag_name' => 'Version(tag)',
            'name' => 'Description',
            'published_at' => 'Release at',
        ];

        try {
            $timezone = nexus_env('TIMEZONE');
        } catch (\Exception $exception) {
            $update->doLog("no .env file, release time is github original");
            $timezone = null;
        }

        try {
            $latestCommit = $update->getLatestCommit();
            $time = \Carbon\Carbon::parse($latestCommit['commit']['committer']['date']);
            if ($timezone) {
                $time->tz = $timezone;
            }
            $tableRows[] = [
                'checkbox' => sprintf('<input type="radio" name="version_url" value="development|%s"/>', $latestCommit['sha']),
                'tag_name' => 'Latest development code',
                'name' => "Development testing only! Latest commit：" . $latestCommit['commit']['message'],
                'published_at' => $time->format('Y-m-d H:i:s'),
            ];
        } catch (\Exception $exception) {
            $update->doLog("can not fetch latest commit from github: " . $exception->getMessage());
        }
        foreach ($versions as $version) {
            if ($version['draft']) {
                continue;
            }
            $time = \Carbon\Carbon::parse($version['published_at']);
            if ($timezone) {
                $time->tz = $timezone;
            }
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
                throw new \RuntimeException("No version selected yet");
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
                    throw new \RuntimeException("Must select a version higher than the current one(" . VERSION_NUMBER . ")");
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
    $envExampleData = readEnvFile($envExampleFile);
    $envFormControls = $update->listEnvFormControls();
    $newData = array_column($envFormControls, 'value', 'name');
    while ($isPost) {
        try {
            $update->createEnvFile($_POST, 'update');
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
    $mysqlInfo = $update->getMysqlVersionInfo();
    $redisInfo = $update->getREdisVersionInfo();
    while ($isPost) {
        set_time_limit(300);
        try {
//            $update->updateDependencies();
            $update->createSymbolicLinks($symbolicLinks);
            $update->saveSettings($settings);
            $update->runExtraQueries();
            $update->runMigrate();
            $update->runExtraMigrate();
            $update->nextStep();
        } catch (\Exception $e) {
            $error = $e->getMessage();
            break;
        }
        break;
    }
}

if (
    !empty($error)
    || (isset($mysqlInfo) && !$mysqlInfo['match'])
    || (isset($redisInfo) && !$redisInfo['match'])
) {
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
                'label' => 'Item',
                'required' => 'Require',
                'current'=> 'Current',
                'result'=> 'Result'
            ];
            if ($currentStep == 1) {
                echo $update->renderTable($header, $requirements['table_rows']);
            } elseif ($currentStep == 3) {
                echo $update->renderTable($header, $tableRows);
                echo '<div class="text-gray-700 p-4 text-red-400">Before the next step, make sure that you have executed <code>composer install</code> in the root directory to update the dependencies.</div>';
                echo $update->renderForm($envFormControls);

            } elseif ($currentStep == 2) {
                if (empty($tableRows)) {
                    echo '<div class="text-green-600 text-center">Sorry, there is no version to choose from at this time!</div>';
                } else {
                    echo $update->renderTable($versionHeader, $tableRows);
                }
            } elseif ($currentStep == 4) {
                echo $update->renderTable($header, $tableRows);
                echo '<div class="text-blue-500 pt-10">';
                echo sprintf('This step will merge <code>%s</code> to <code>%s</code>, then insert into database.', $tableRows[1]['label'], $tableRows[0]['label']);
                echo '</div>';
                if (!$mysqlInfo['match']) {
                    echo sprintf('<div class="text-red-700 pt-10">MySQL version: %s is too low, please use the newest version of 5.7 or above.</div>', $mysqlInfo['version']);
                }
                if (!$redisInfo['match']) {
                    echo sprintf('<div class="text-red-700 pt-10">Redis version: %s is too low, please use 2.0.0 or above.</div>', $redisInfo['version']);
                }
            } elseif ($currentStep > $maxStep) {
                echo '<div class="text-green-900 text-6xl p-10">Congratulations, everything is ready!</div>';
                echo '<div class="mb-6">For questions, consult the upgrade log at: <code>' . $update->getLogFile() . '</code></div>';
                echo '<div class="text-red-500">For security reasons, please delete the following directories</div>';
                echo '<div class="text-red-500"><code>' . $update->getUpdateDirectory() . '</code></div>';
                $update->setLock();
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
            <div class="mt-2 text-center">
                <button class="bg-blue-500 p-2 m-4 text-white rounded" type="button" onclick="goBack()">Prev</button>
                <?php if ($currentStep <= $maxStep) {?>
                    <button class="bg-blue-<?php echo $pass ? 500 : 200;?> p-2 m-4 text-white rounded" type="submit" <?php echo $pass ? '' : 'disabled';?>>Next</button>
                <?php } else {?>
                    <a class="bg-blue-500 p-2 m-4 text-white rounded" href="<?php echo getSchemeAndHttpHost()?>">Go to homepage</a>
                <?php }?>
            </div>
        </form>
    </div>
</div>
<div class="m-2 text-center">
    Welcome to the NexusPHP updater, if you have any questions, click<a href="https://nexusphp.org/" target="_blank" class="text-blue-500 p-1">here</a>for help.
</div>
</body>
<script>
    function goBack() {
        window.location.search="step=<?php echo $currentStep == 1 ? 1 : $currentStep - 1?>"
    }
</script>
</html>
