<?php

namespace Nexus\Plugin;

class Plugin
{
    private static mixed $providers = null;

    /**
     * @var BasePlugin[]
     */
    private static array $plugins = [];

    //    public function __construct()
    //    {
    //        $this->start();
    //    }

    public function start(): void
    {
        $this->loadProviders();
        $this->bootPlugins();
    }

    public static function enabled($name): bool
    {
        return ! empty(self::$providers[$name]['providers']);
    }

    public static function listEnabled(): array
    {
        $result = [];
        // plugins are more exactly
        foreach (self::$plugins as $id => $plugin) {
            $result[$id] = $plugin->getVersion();
        }

        return $result;
    }

    public static function getById($id): ?BasePlugin
    {
        return self::$plugins[$id] ?? null;
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
        foreach (self::$providers as $providers) {
            if (! isset($providers['providers'])) {
                continue;
            }
            $provider = $providers['providers'][0];
            $parts = explode('\\', $provider);
            if ($parts[0] == 'NexusPlugin') {
                $className = str_replace('ServiceProvider', 'Repository', $provider);
                if (class_exists($className)) {
                    $constantName = "$className::COMPATIBLE_NP_VERSION";
                    if (defined($constantName) && version_compare(VERSION_NUMBER, constant($constantName), '<')) {
                        do_log(sprintf('class: %s require NP_VERSION: %s > current: %s', $className, constant($constantName), VERSION_NUMBER), 'error');

                        continue;
                    }
                    /**
                     * @var BasePlugin $className
                     */
                    $plugin = new $className;
                    //                    $pluginIdName = "$className::ID";
                    //                    if (defined($pluginIdName)) {
                    //                        self::$plugins[constant($pluginIdName)] = $plugin;
                    //                    }
                    self::$plugins[$plugin->getId()] = $plugin;
                    call_user_func([$plugin, 'boot']);
                }
            }
        }
    }

    private function loadProviders()
    {
        if (is_null(self::$providers)) {
            $path = ROOT_PATH.'bootstrap/cache/packages.php';
            if (file_exists($path)) {
                self::$providers = require $path;
            } else {
                self::$providers = [];
            }
        }
    }
}
