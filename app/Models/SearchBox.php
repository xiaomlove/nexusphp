<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

class SearchBox extends NexusModel
{
    protected $table = 'searchbox';

    protected $fillable = [
        'name', 'catsperrow', 'catpadding', 'showsubcat',
        'showsource', 'showmedium', 'showcodec', 'showstandard', 'showprocessing', 'showteam', 'showaudiocodec',
        'custom_fields', 'custom_fields_display_name', 'custom_fields_display', 'extra'
    ];

    protected $casts = [
        'extra' => 'object'
    ];

    const EXTRA_DISPLAY_COVER_ON_TORRENT_LIST = 'display_cover_on_torrent_list';
    const EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST = 'display_seed_box_icon_on_torrent_list';

    public static array $extras = [
        self::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST => ['text' => 'Display cover on torrent list'],
        self::EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST => ['text' => 'Display seed box icon on torrent list'],
    ];

    public static function listExtraText(): array
    {
        $result = [];
        foreach (self::$extras as $extra => $info) {
            $result[$extra] = nexus_trans("searchbox.extras.$extra");
        }
        return $result;
    }

    public function getCustomFieldsAttribute($value): array
    {
        if (!is_array($value)) {
            return explode(',', $value);
        }
    }

    public function setCustomFieldsAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['custom_fields'] = implode(',', $value);
        }
    }


    public function categories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Category::class, 'mode');
    }

    public function normal_fields(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SearchBoxField::class, 'searchbox_id');
    }

}
