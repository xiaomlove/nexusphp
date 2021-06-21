<?php

namespace App\Models;


class Category extends NexusModel
{
    protected $table = 'categories';

    protected $fillable = ['mode', 'name', 'class_name', 'image', 'sort_index', 'icon_id'];

    public static function getLabelName()
    {
        return nexus_trans('searchbox.category_label');
    }

    public function icon(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Icon::class, 'icon_id');
    }
}
