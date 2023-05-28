<?php

namespace App\Models;

class Language extends NexusModel
{
    const DEFAULT_ENABLED = ['en', 'chs', 'cht'];

    protected $table = 'language';

    protected $fillable = [
        'lang_name', 'site_lang_folder',
    ];

    public static function listEnabled($withoutCache = false)
    {
        if ($withoutCache) {
            return Setting::getFromDb('main.site_language_enabled', self::DEFAULT_ENABLED);
        }
        return Setting::get('main.site_language_enabled', self::DEFAULT_ENABLED);
    }
}
