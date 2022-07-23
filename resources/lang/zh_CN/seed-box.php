<?php

return [
    'type_text' => [
        \App\Models\SeedBoxRecord::TYPE_USER => '用户',
        \App\Models\SeedBoxRecord::TYPE_ADMIN => '管理员',
    ],
    'status_text' => [
        \App\Models\SeedBoxRecord::STATUS_UNAUDITED => '未审核',
        \App\Models\SeedBoxRecord::STATUS_ALLOWED => '已通过',
        \App\Models\SeedBoxRecord::STATUS_DENIED => '已拒绝',
    ],
    'status_change_message' => [
        'subject' => 'SeedBox 记录状态变更',
        'body' => '你的 ID 为 :id 的 SeedBox 记录状态被 :operator 由 :old_status 变更为 :new_status',
    ],
];
