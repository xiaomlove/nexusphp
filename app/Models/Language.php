<?php

namespace App\Models;

class Language extends NexusModel
{
    protected $table = 'language';

    protected $fillable = [
        'lang_name', 'site_lang_folder',
    ];
}
