<?php

return [
    'category_label' => 'Category',
    'sub_category_source_label' => 'Source',
    'sub_category_medium_label' => 'Media',
    'sub_category_standard_label' => 'Standard',
    'sub_category_team_label' => 'Team',
    'sub_category_processing_label' => 'Processing',
    'sub_category_codec_label' => 'Codec',
    'sub_category_audiocodec_label' => 'AudioCodec',
    'extras' => [
        \App\Models\SearchBox::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST => 'Display cover on torrent list',
        \App\Models\SearchBox::EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST => 'Display seed box icon on torrent list',
    ],
    'sections' => [
        'browse' => 'Torrents',
        'special' => 'Special',
    ],
];
