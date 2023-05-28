<?php
namespace Nexus\Plugin;

use App\Repositories\BaseRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;

abstract class BasePlugin extends BaseRepository
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

    public function checkMainApplicationVersion()
    {
        $constantName = "static::COMPATIBLE_VERSION";
        if (defined($constantName) && version_compare(VERSION_NUMBER, constant($constantName), '<')) {
            throw new \RuntimeException(sprintf(
                "NexusPHP version: %s is too low, this plugin require: %s",
                VERSION_NUMBER, constant($constantName)
            ));
        }
    }
}
