<?php

namespace App\Models;

class SearchBox extends NexusModel
{
    protected $table = 'searchbox';

    protected $fillable = [
        'name', 'catsperrow', 'catpadding', 'showsubcat',
        'showsource', 'showmedium', 'showcodec', 'showstandard', 'showprocessing', 'showteam', 'showaudiocodec',
        'custom_fields', 'custom_fields_display_name', 'custom_fields_display'
    ];

    public function categories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Category::class, 'mode');
    }

    public function normal_fields(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SearchBoxField::class, 'searchbox_id');
    }

}
