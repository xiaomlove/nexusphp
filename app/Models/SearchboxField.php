<?php

namespace App\Models;

class SearchboxField extends NexusModel
{
    protected $table = 'searchbox_fields';

    protected $fillable = ['searchbox_id', 'field_type', 'field_id', ];

    public function searchbox()
    {
        return $this->belongsTo(Searchbox::class, 'searchbox_id');
    }
}
