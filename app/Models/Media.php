<?php

namespace App\Models;


class Media extends NexusModel
{
    protected $table = 'media';

    public static function getLabelName()
    {
        return nexus_trans('searchbox.sub_category_media_label');
    }
}
