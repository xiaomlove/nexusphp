<?php

return [
    'actions' => [
        'install' => 'Install',
        'delete' => 'Remove',
        'update' => 'Upgrade',
    ],
    'labels' => [
        'display_name' => 'Name',
        'package_name' => 'Package name',
        'remote_url' => 'Repository address',
        'installed_version' => 'Installed version',
        'status' => 'Status',
        'updated_at' => 'Last action at',
    ],
    'status' => [
        \App\Models\Plugin::STATUS_NORMAL => 'Normal',
        \App\Models\Plugin::STATUS_NOT_INSTALLED => 'Not installed',

        \App\Models\Plugin::STATUS_PRE_INSTALL => 'Ready to install',
        \App\Models\Plugin::STATUS_INSTALLING => 'Installing',
        \App\Models\Plugin::STATUS_INSTALL_FAILED => 'Install fail',

        \App\Models\Plugin::STATUS_PRE_UPDATE => 'Ready to upgrade',
        \App\Models\Plugin::STATUS_UPDATING => 'Upgrading',
        \App\Models\Plugin::STATUS_UPDATE_FAILED => 'Upgrade fail',

        \App\Models\Plugin::STATUS_PRE_DELETE => 'Ready to remove',
        \App\Models\Plugin::STATUS_DELETING => 'Removing',
        \App\Models\Plugin::STATUS_DELETE_FAILED => 'Remove fail',
    ],
];
