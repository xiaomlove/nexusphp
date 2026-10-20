<?php

namespace NexusPlugin\BankSystem;

class BankSystem
{
    /**
     * 插件ID
     */
    const ID = 'nexusphp-bank-system';

    /**
     * 创建插件实例
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * 获取插件ID
     */
    public function getId(): string
    {
        return self::ID;
    }
}
