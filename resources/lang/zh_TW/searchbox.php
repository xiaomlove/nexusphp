<?php

return [
    'category_label' => '分類',
    'sub_category_source_label' => '來源',
    'sub_category_medium_label' => '媒介',
    'sub_category_standard_label' => '分辨率',
    'sub_category_team_label' => '製作組',
    'sub_category_processing_label' => '處理',
    'sub_category_codec_label' => '編碼',
    'sub_category_audiocodec_label' => '音頻編碼',
    'extras' => [
        \App\Models\SearchBox::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST => '種子列表頁展示封面',
        \App\Models\SearchBox::EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST => '種子列表頁展示 SeedBox 圖標',
    ],
    'sections' => [
        'browse' => '種子區',
        'special' => '特別區',
    ],
];
