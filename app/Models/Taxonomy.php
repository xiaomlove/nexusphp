<?php

namespace App\Models;


class Taxonomy extends NexusModel
{
    protected $fillable = [
        'mode', 'name', 'torrent_field', 'image', 'class_name', 'priority',
    ];
}
