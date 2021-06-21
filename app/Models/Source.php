<?php

namespace App\Models;


class Source extends NexusModel
{
    public static function getLabelName()
    {
        return nexus_trans('searchbox.sub_category_source_label');
    }
}
