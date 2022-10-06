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
        'subject' => 'Download permission canceled',
        'body' => 'Your download privileges has revoked, possibly due to low sharing rates or misbehavior. By: :operator',
    ],
    'download_disable_upload_over_speed' => [
        'subject' => 'Download permission canceled',
        'body' => 'Your download permission has been cancelled due to excessive upload speed, please file if you are a seed box user.' ,
    ],
    'download_enable' => [
        'subject' => 'Download permission restored',
        'body' => 'Your download privileges restored, you can now download torrents. By: :operator',
    ],
];
