<?php

namespace App\Models;


class Standard extends NexusModel
{
    protected $fillable = ['name', 'sort_index'];

    public static function getLabelName()
    {
        return nexus_trans('searchbox.sub_category_standard_label');
    }
}
