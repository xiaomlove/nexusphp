<?php
namespace Nexus\Plugin;

use App\Repositories\BaseRepository;
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

    public static function checkMainApplicationVersion($silent = true): bool
    {
        $constantNameArr = [
            "static::COMPATIBLE_NP_VERSION",
            "static::COMPATIBLE_VERSION", //before use
        ];
        foreach ($constantNameArr as $constantName) {
            if (defined($constantName) && version_compare(VERSION_NUMBER, constant($constantName), '<')) {
                if ($silent) {
                    return false;
                }
                throw new \RuntimeException(sprintf(
                    "NexusPHP version: %s is too low, this plugin require: %s",
                    VERSION_NUMBER, constant($constantName)
                ));
            }
        }
        return true;
    }

    public function getNexusView($name): string
    {
        $reflection = new \ReflectionClass(get_called_class());
        $pluginRoot = dirname($reflection->getFileName(), 2);
        return $pluginRoot . "/resources/views/" . trim($name, "/");
    }

    public function trans($name): string
    {
        return nexus_trans($this->getTransKey($name));
    }

    public function getTransKey($name): string
    {
        return sprintf("%s::%s", self::resolveId(), $name);
    }

    public static function getInstance(): static
    {
        return Plugin::getById(self::resolveId());
    }

    /**
     * Resolve the plugin ID constant on the concrete subclass.
     *
     * Mirrors the lookup pattern used by getVersion() for VERSION:
     * checks defined() first so a missing const surfaces a clear
     * error rather than the misleading "Undefined constant" PHP
     * fatal.
     */
    private static function resolveId(): string
    {
        $constantName = static::class . '::ID';
        if (! defined($constantName)) {
            throw new \LogicException(sprintf(
                'Plugin %s must declare a public ID constant.',
                static::class
            ));
        }
        return (string) constant($constantName);
    }

    public function getVersion(): string
    {
        $constantName = "static::VERSION";
        return defined($constantName) ? constant($constantName) : '';
    }

    public function getId(): string
    {
        $className = str_replace("Repository", "", get_called_class());
        $plugin = call_user_func([$className, "make"]);
        return $plugin->getId();
    }
}
