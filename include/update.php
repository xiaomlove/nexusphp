<?php
/**
 * update 1.5 to 1.6, run in console
 * @author xiaomlove<1939737565@qq.com>
 */

function printLine($msg, $exit = false)
{
    echo sprintf("[%s] %s%s", date('Y-m-d H:i:s'), $msg, PHP_EOL);
    if ($exit) {
        exit(0);
    }
}

if (php_sapi_name() != 'cli') {
    printLine("Please run in console!", true);
}

//environment check
printLine("Checking the environment...");
if (version_compare(PHP_VERSION, '7.2.0', '<')) {
    printLine(sprintf('Your PHP version: %s not match, require >= 7.2.0', PHP_VERSION));
}
$requireExtensions = ['mysqli', 'mbstring', 'gd', 'json'];
foreach ($requireExtensions as $ext) {
    if (extension_loaded($ext)) {
        printLine("extension: $ext is loaded.");
    } else {
        printLine("Error: required extension: $ext is not loaded!", true);
    }
}
if (!extension_loaded('redis')) {
    printLine("warning: redis is not loaded. highly recommand install it, see: https://pecl.php.net/package/redis/");
}
$gdInfo = gd_info();
$gdShouldAvaliable = ['JPEG Support', 'PNG Support', 'GIF Read Support'];
foreach ($gdShouldAvaliable as $value) {
    if (!isset($gdInfo[$value]) || !$gdInfo[$value]) {
        printLine("Error: gd $value is not available", true);
    }
}

//create .env file
$rootPath = dirname(__DIR__);
$envExample = "$rootPath/.env.example";
$env = "$rootPath/.env";
if (!file_exists("$rootPath.env")) {
    printLine("Error: $envExample not exists.");
}
$copyResult = copy($envExample, $env);
if ($copyResult !== true) {

}





