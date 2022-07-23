<?php

return [
    'type_text' => [
        \App\Models\SeedBoxRecord::TYPE_USER => 'User',
        \App\Models\SeedBoxRecord::TYPE_ADMIN => 'Administrator',
    ],
    'status_text' => [
        \App\Models\SeedBoxRecord::STATUS_UNAUDITED => 'Unaudited',
        \App\Models\SeedBoxRecord::STATUS_ALLOWED => 'Allowed',
        \App\Models\SeedBoxRecord::STATUS_DENIED => 'Denied',
    ],
    'status_change_message' => [
        'subject' => 'SeedBox record status changed',
        'body' => 'The status of your SeedBox record with ID :id was changed by :operator from :old_status to :new_status',
    ],
];
