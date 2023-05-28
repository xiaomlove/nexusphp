<?php

namespace App\Models;


class Source extends NexusModel
{
    protected $fillable = ['name', 'sort_index', 'mode',];

    public static function getLabelName()
    {
        return nexus_trans('searchbox.sub_category_source_label');
    }

    public function search_box()
    {
        return $this->belongsTo(SearchBox::class, 'mode', 'id');
    }
}
