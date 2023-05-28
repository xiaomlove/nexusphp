<?php

namespace App\Models;


class SecondIcon extends NexusModel
{
    protected $table = 'secondicons';

    protected $fillable = [
        'name', 'class_name', 'image', 'mode',
        'source', 'medium', 'codec', 'audiocodec', 'standard', 'processing', 'team'
    ];

    public static function formatFormData(array $data): array
    {
        foreach (SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
            $mode = $data['mode'];
            if ($mode === null || empty($data[$torrentField][$mode])) {
                $data[$torrentField] = 0;
            } else {
                $data[$torrentField] = $data[$torrentField][$mode];
            }
        }
        if ($data['mode'] === null) {
            $data['mode'] = 0;
        }
        return $data;
    }

    public function search_box()
    {
        return $this->belongsTo(SearchBox::class, 'mode', 'id');
    }
}
