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
        foreach (SearchBox::$taxonomies as $torrentField => $table) {
            $mode = $data['mode'];
            if (empty($data[$torrentField][$mode])) {
                unset($data[$torrentField]);
            } else {
                $data[$torrentField] = $data[$torrentField][$mode];
            }
        }
        return $data;
    }

    public function search_box()
    {
        return $this->belongsTo(SearchBox::class, 'mode', 'id');
    }
}
