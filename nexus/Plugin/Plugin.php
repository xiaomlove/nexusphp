<?php
namespace Nexus\Plugin;

class Plugin
{
    private static mixed $providers = null;

    public function __construct()
    {
        $this->loadProviders();
        $this->bootPlugins();
    }

    public function enabled($name): bool
    {
        return !empty(self::$providers[$name]['providers']);
    }

    public function getMainClass($name)
    {
        if (isset(self::$providers[$name]['providers'][0])) {
            $className = self::$providers[$name]['providers'][0];
            $className = str_replace('ServiceProvider', 'Repository', $className);
            if (class_exists($className)) {
                return new $className;
            }
        }
    }

    private function bootPlugins()
    {
        foreach (self::$providers as $name => $providers) {
            $provider = $providers['providers'][0];
            $parts = explode('\\', $provider);
            if ($parts[0] == 'NexusPlugin') {
                $className = str_replace('ServiceProvider', 'Repository', $provider);
                if (class_exists($className)) {
                    call_user_func([new $className, 'boot']);
                } else {
                    do_log("Plugin: $className not exists.", "error");
                }
            }
        }
    }

    private function loadProviders()
    {
        if (is_null(self::$providers)) {
            $path = ROOT_PATH . 'bootstrap/cache/packages.php';
            if (file_exists($path)) {
                self::$providers = require $path;
            } else {
                self::$providers = [];
            }
        }
    }


}
