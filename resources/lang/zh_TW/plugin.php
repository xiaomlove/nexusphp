<?php

return [
    'actions' => [
        'install' => '安裝',
        'delete' => '刪除',
        'update' => '升級',
    ],
    'labels' => [
        'display_name' => '名稱',
        'package_name' => '包名',
        'remote_url' => '倉庫地址',
        'installed_version' => '已安裝版本',
        'status' => '狀態',
        'updated_at' => '上次執行操作',
    ],
    'status' => [
        \App\Models\Plugin::STATUS_NORMAL => '正常',
        \App\Models\Plugin::STATUS_NOT_INSTALLED => '未安裝',

        \App\Models\Plugin::STATUS_PRE_INSTALL => '準備安裝',
        \App\Models\Plugin::STATUS_INSTALLING => '安裝中',
        \App\Models\Plugin::STATUS_INSTALL_FAILED => '安裝失敗',

        \App\Models\Plugin::STATUS_PRE_UPDATE => '準備升級',
        \App\Models\Plugin::STATUS_UPDATING => '升級中',
        \App\Models\Plugin::STATUS_UPDATE_FAILED => '升級失敗',

        \App\Models\Plugin::STATUS_PRE_DELETE => '準備刪除',
        \App\Models\Plugin::STATUS_DELETING => '刪除中',
        \App\Models\Plugin::STATUS_DELETE_FAILED => '刪除失敗',
    ],
];
