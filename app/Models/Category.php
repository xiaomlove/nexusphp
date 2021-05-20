<?php

namespace App\Models;


class Category extends NexusModel
{
    protected $table = 'categories';

    protected $fillable = ['mode', 'name', 'class_name', 'image', 'sort_index', 'icon_id'];
}
