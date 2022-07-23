<?php

return [

    'index' => [
        'page_title' => '私信列表',
    ],
    'show' => [
        'page_title' => '私信詳情',
    ],
    'field_value_change_message_body' => ':field 被管理員 :operator 從 :old 改為 :new。理由：:reason。',
    'field_value_change_message_subject' => ':field 改變',
    'download_disable' => [
        'subject' => '下載權限取消',
        'body' => '管理員：:operator 取消了你的下載權限，可能的原因是過低的分享率或行為不當。',
    ],
    'download_enable' => [
        'subject' => '下載權限恢復',
        'body' => '管理員：:operator 恢復了你的下載權限，你現在可以下載種子。',
    ],
];
