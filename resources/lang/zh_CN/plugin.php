<?php

return [
    'actions' => [
        'install' => '安装',
        'delete' => '删除',
        'update' => '升级',
    ],
    'labels' => [
        'display_name' => '名称',
        'package_name' => '包名',
        'remote_url' => '仓库地址',
        'installed_version' => '已安装版本',
        'status' => '状态',
        'updated_at' => '上次执行操作',
    ],
    'status' => [
        \App\Models\Plugin::STATUS_NORMAL => '正常',
        \App\Models\Plugin::STATUS_NOT_INSTALLED => '未安装',

        \App\Models\Plugin::STATUS_PRE_INSTALL => '准备安装',
        \App\Models\Plugin::STATUS_INSTALLING => '安装中',
        \App\Models\Plugin::STATUS_INSTALL_FAILED => '安装失败',

        \App\Models\Plugin::STATUS_PRE_UPDATE => '准备升级',
        \App\Models\Plugin::STATUS_UPDATING => '升级中',
        \App\Models\Plugin::STATUS_UPDATE_FAILED => '升级失败',

        \App\Models\Plugin::STATUS_PRE_DELETE => '准备删除',
        \App\Models\Plugin::STATUS_DELETING => '删除中',
        \App\Models\Plugin::STATUS_DELETE_FAILED => '删除失败',
    ],
];
