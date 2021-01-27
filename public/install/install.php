<?php
if (!session_id()) {
    session_start();
}
$rootpath = dirname(dirname(__DIR__)) . '/';
define('ROOT_PATH', $rootpath);
$isPost = $_SERVER['REQUEST_METHOD'] == 'POST';
require $rootpath . 'vendor/autoload.php';
require $rootpath . 'include/globalfunctions.php';
require $rootpath . 'include/functions.php';
require $rootpath . 'nexus/Database/helpers.php';

$install = new \Nexus\Install\Install();
$currentStep = $install->currentStep();
$maxStep = $install->maxStep();

//step 1
if ($currentStep == 1) {
    $requirements = $install->listRequirementTableRows();
    $pass = $requirements['pass'];
    if ($isPost) {
        $install->nextStep();
    }
}

if ($currentStep == 2) {
    $envExampleFile = "$rootpath.env.example";
    $envExampleData = readEnvFile($envExampleFile);
    $envFormControls = $install->listEnvFormControls();
    $newData = array_column($envFormControls, 'value', 'name');
    while ($isPost) {
//        $envFile = "$rootpath.env." . time();
//        $newData = $formData = [];
//        if (file_exists($envFile) && is_readable($envFile)) {
//            //already exists, read it ,and merge post data
//            $newData = readEnvFile($envFile);
//        }
//        foreach ($envExampleData as $key => $value) {
//            $postValue = trim($_POST[$key] ?? '');
//            if ($postValue) {
//                $value = $postValue;
//            }
//            $newData[$key] = $value;
//            $envExampleData[] = [
//                'label' => $key,
//                'name' => $key,
//                'value' => $value,
//            ];
//        }
//        unset($key);
        //check
        try {
            $connectMysql = mysql_connect($newData['MYSQL_HOST'], $newData['MYSQL_USERNAME'], $newData['MYSQL_PASSWORD'], $newData['MYSQL_DATABASE'], $newData['MYSQL_PORT']);
        } catch (\Exception $e) {
            $_SESSION['error'] = "Mysql: " . $e->getMessage();
            break;
        }
        if (extension_loaded('redis') && !empty($newData['REDIS_HOST'])) {
            try {
                $redis = new Redis();
                $redis->connect($newData['REDIS_HOST'], $newData['REDIS_PORT'] ?: 6379);
            } catch (\Exception $e) {
                $_SESSION['error'] = "Redis: " . $e->getMessage();
                break;
            }
            if (!ctype_digit($newData['REDIS_DATABASE']) || $newData['REDIS_DATABASE'] < 0 || $newData['REDIS_DATABASE'] > 15) {
                $_SESSION['error'] = "invalid REDIS_DATABASE";
                break;
            }
        }

//        $content = "";
//        foreach ($newData as $key => $value) {
//            $content .= "{$key}={$value}\n";
//        }
//        $fp = @fopen($envFile, 'w');
//        if ($fp === false) {
//            $msg = "文件无法打开, 确保 PHP 有权限在根目录创建文件";
//            $msg .= "\n也可以复制以下内容保存到 $envFile 中";
//            $_SESSION['error'] = $msg;
//            $_SESSION['copy'] = $content;
//        }
//        fwrite($fp, $content);
//        fclose($fp);
        $install->nextStep();
        break;
    }

    $tableRows = [
        [
            'label' => '.env.example',
            'required' => 'exists && readable',
            'current' => $envExampleFile,
            'result' =>  $install->yesOrNo(file_exists($envExampleFile) && is_readable($envExampleFile)),
        ],
    ];
    $fails = array_filter($tableRows, function ($value) {return $value['result'] == 'NO';});
    $pass = empty($fails);
}

if ($currentStep == 3) {
    $pass = true;
    $existsTable = $install->listExistsTable();
    $tableCreate = $install->listAllTableCreate();
    $shouldCreateTable = [];
    foreach ($tableCreate as $table => $sql) {
        if (in_array($table, $existsTable)) {
            continue;
        }
        $shouldCreateTable[$table] = $sql;
    }

    while (true) {
//        $sqlFile = $rootpath  . '_db/dbstructure_v1.6.sql';
//        try {
//            $tableCreate = listAllTableCreate($sqlFile);
//        } catch (\Exception $e) {
//            $_SESSION['error'] = $e->getMessage();
//            break;
//        }
//        if (empty($tableCreate)) {
//            $_SESSION['error'] = "no table create, make sure sql file is correct";
//            break;
//        }

        if ($isPost) {
            try {
                foreach ($shouldCreateTable as $table => $sql) {
                    sql_query($sql);
                }
            } catch (\Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                break;
            }
            $install->nextStep();
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
//                    $_SESSION['error'] = $e->getMessage();
//                    break;
//                }
//            }
//            goStep($currentStep + 1);
//        }
//        break;
//    }
//}

if ($currentStep == 4) {
    $settingTableRows = $install->listSettingTableRows();
    $settings = $settingTableRows['settings'];
    $symbolicLinks = $settingTableRows['symbolic_links'];
    $tableRows = $settingTableRows['table_rows'];
    $pass = $settingTableRows['pass'];
    while (true) {
        try {
            foreach ($settings as $prefix => &$group) {
                if ($isPost) {
                    $install->doLog("[SAVE] prefix: $prefix, nameAndValues: " . json_encode($group));
                    foreach ($symbolicLinks as $path) {
                        $linkName = ROOT_PATH . 'public/' . basename($path);
                        if (is_dir($linkName)) {
                            $install->doLog("path: $linkName already exits, skip create symbolic link $linkName -> $path");
                            continue;
                        }
                        //@todo
//                        $linkResult = symlink($path, $linkName);
//                        if ($linkResult === false) {
//                            throw new \Exception("can't not make symbolic link:  $linkName -> $path");
//                        }
                    }
                    //@todo
//                    saveSetting($prefix, $group);
                }
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            break;
        }
        $isPost && $install->nextStep();
        break;
    }
}

if ($currentStep == 5) {
    if ($isPost) {
        try {
            $install->createAdministrator($_POST['username'], $_POST['email'], $_POST['password'], $_POST['confirm_password']);
            $install->nextStep();
        } catch (\Exception $exception) {
            $_SESSION['error'] = $exception->getMessage();
        }
    }
    $pass = true;
    $userFormControls = [
        ['label' => '用户名', 'name' => 'username', 'value' => $_POST['username'] ?? ''],
        ['label' => '邮箱', 'name' => 'email', 'value' => $_POST['email'] ?? ''],
        ['label' => '密码', 'name' => 'password', 'value' => $_POST['password'] ?? ''],
        ['label' => '重复密码', 'name' => 'confirm_password', 'value' => $_POST['confirm_password'] ?? ''],
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
    <title>Update NexusPHP | step <?php echo $currentStep?></title>
      <style type="text/css">
          .step .none {
              color: #aaa;
          }
          .step .done {
              color: #67c23a;
          }
      </style>
  </head>
  <body>
      <div class="container mx-auto">
          <?php echo $install->renderSteps()?>
          <div class="mt-10">
              <form method="post" action="<?php echo getBaseUrl() . '?step=' . $currentStep?>">
              <input type="hidden" name="step" value="<?php echo $currentStep?>">
              <?php
              echo'<div class="step-' . $currentStep . ' text-center">';
              $header = ['项目', '要求', '当前', '结果'];
                if ($currentStep == 1) {
                    echo $install->renderTable($header, $requirements['table_rows']);
                } elseif ($currentStep == 2) {
                    echo $install->renderTable($header, $tableRows);
                    echo '<div class="text-gray-700 p-4 text-red-400">若 Redis 不启用，相关项目留空</div>';
                    echo $install->renderForm($envFormControls);

                } elseif ($currentStep == 3) {
                    echo '<h1 class="mb-4 text-lg font-bold">需要新建以下数据表</h1>';
                    if (empty($shouldCreateTable)) {
                        echo '<div class="text-green-600 text-center">恭喜，需要的表均已创建!</div>';
                    } else {
                        echo sprintf('<div class="h-64 text-left inline-block w-2/3"><code class="bolck w-px-100">%s</code></div>', implode(', ', array_keys($shouldCreateTable)));
                    }
                } elseif ($currentStep == 4) {
                    echo $install->renderTable($header, $tableRows);
                    echo '<div class="text-blue-500 pt-10">';
                    echo sprintf('这一步会把 <code>%s</code> 的数据合并到 <code>%s</code>, 然后插入数据库中。', $tableRows[1]['label'], $tableRows[0]['label']);
                    echo '</div>';
                } elseif ($currentStep == 5) {
                    echo $install->renderForm($userFormControls, '1/3', '1/4', '3/4');
                } elseif ($currentStep > $maxStep) {
                    echo '<div class="text-green-900 text-6xl p-10">恭喜，一切就绪！</div>';
                    echo '<div class="mb-6">有问题可查阅安装日志：<code>' . $install->getLogFile() . '</code></div>';
                    echo '<div class="text-red-500">为安全起见，请删除以下目录</div>';
                    echo '<div class="text-red-500"><code>' . $install->getInsallDirectory() . '</code></div>';
                }
                echo'</div>';

              if (!empty($_SESSION['error'])) {
                  echo sprintf('<div class="text-center text-red-500 p-4">Error: %s</div>', nl2br($_SESSION['error']));
                  unset($_SESSION['error']);
              }
              if (!empty($_SESSION['copy'])) {
                  echo sprintf('<div class="text-center"><textarea class="w-1/2 h-40 border">%s</textarea></div>', $_SESSION['copy']);
                  unset($_SESSION['copy']);
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
  </body>
<script>
    function goBack() {
        window.location.search="step=<?php echo $currentStep == 1 ? 1 : $currentStep - 1?>"
    }
</script>
</html>