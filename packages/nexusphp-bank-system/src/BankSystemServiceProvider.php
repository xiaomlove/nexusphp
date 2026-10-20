<?php

namespace NexusPlugin\BankSystem;

use Illuminate\Support\ServiceProvider;

class BankSystemServiceProvider extends ServiceProvider
{
    /**
     * 插件ID，必须唯一
     */
    const ID = 'nexusphp-bank-system';

    /**
     * 插件版本
     */
    const VERSION = '1.0.0';

    /**
     * 兼容的NexusPHP版本
     */
    const COMPATIBLE_NP_VERSION = '1.7.21';

    /**
     * Register services.
     */
    public function register(): void
    {
        // 注册服务
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 插件通过Hook系统集成到NexusPHP
        // 不需要在这里注册Filament资源
    }

    /**
     * 获取插件ID
     */
    public static function getId(): string
    {
        return self::ID;
    }

    /**
     * 创建插件实例
     */
    public static function make(): self
    {
        return new self(app());
    }
}
