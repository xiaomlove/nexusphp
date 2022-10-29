<?php

return [
    'category_label' => '分类',
    'sub_category_source_label' => '来源',
    'sub_category_medium_label' => '媒介',
    'sub_category_standard_label' => '分辨率',
    'sub_category_team_label' => '制作组',
    'sub_category_processing_label' => '处理',
    'sub_category_codec_label' => '编码',
    'sub_category_audiocodec_label' => '音频编码',
    'extras' => [
        \App\Models\SearchBox::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST => '种子列表页展示封面',
        \App\Models\SearchBox::EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST => '种子列表页展示 SeedBox 图标',
    ],
    'sections' => [
        'browse' => '种子区',
        'special' => '特别区',
    ],
];
