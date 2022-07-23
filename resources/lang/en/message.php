<?php

return [

    'index' => [
        'page_title' => 'Message list',
    ],
    'show' => [
        'page_title' => 'Message detail',
    ],
    'field_value_change_message_body' => ':field is changed from :old to :new by :operator. Reason：:reason.',
    'field_value_change_message_subject' => ':field changed',

    'download_disable' => [
        'subject' => 'Download permission cancellation',
        'body' => 'Administrator: :operator has revoked your download privileges, possibly due to low sharing rates or misbehavior.',
    ],
    'download_enable' => [
        'subject' => 'Download permission restored',
        'body' => 'Administrator: :operator restored your download privileges, you can now download torrents.',
    ],
];
