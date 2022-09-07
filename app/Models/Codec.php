<?php

namespace App\Models;


class Codec extends NexusModel
{
    protected $table = 'codecs';

    protected $fillable = ['name', 'sort_index'];

    public static function getLabelName()
    {
        return nexus_trans('searchbox.sub_category_codec_label');
    }
}
