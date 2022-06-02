<?php
namespace Nexus\Plugin;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;

abstract class BasePlugin
{
    abstract function install();

    abstract function boot();

    public function runMigrations($dir, $rollback = false)
    {
        $command = "migrate";
        if ($rollback) {
            $command .= ":rollback";
        }
        $command .= " --realpath --force";
        foreach (glob("$dir/*.php") as $file) {
            $file = str_replace('\\', '/', $file);
            $toExecute = "$command --path=$file";
            do_log("command: $toExecute");
            Artisan::call($toExecute);
        }
    }
}
