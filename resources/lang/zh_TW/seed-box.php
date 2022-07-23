<?php

return [
    'type_text' => [
        \App\Models\SeedBoxRecord::TYPE_USER => '用戶',
        \App\Models\SeedBoxRecord::TYPE_ADMIN => '管理員',
    ],
    'status_text' => [
        \App\Models\SeedBoxRecord::STATUS_UNAUDITED => '未審核',
        \App\Models\SeedBoxRecord::STATUS_ALLOWED => '已通過',
        \App\Models\SeedBoxRecord::STATUS_DENIED => '已拒絕',
    ],
    'status_change_message' => [
        'subject' => 'SeedBox 記錄狀態變更',
        'body' => '你的 ID 為 :id 的 SeedBox 記錄狀態被 :operator 由 :old_status 變更為 :new_status',
    ],
];
