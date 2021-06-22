<?php

namespace App\Models;


class Processing extends NexusModel
{
    protected $table = 'processings';

    public static function getLabelName()
    {
        return nexus_trans('searchbox.sub_category_processing_label');
    }
}
