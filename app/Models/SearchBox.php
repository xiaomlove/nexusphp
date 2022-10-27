<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Nexus\Database\NexusDB;

class SearchBox extends NexusModel
{
    private static array $instances = [];

    protected $table = 'searchbox';

    protected $fillable = [
        'name', 'catsperrow', 'catpadding', 'showsubcat', 'section_name', 'is_default',
        'showsource', 'showmedium', 'showcodec', 'showstandard', 'showprocessing', 'showteam', 'showaudiocodec',
        'custom_fields', 'custom_fields_display_name', 'custom_fields_display',
        'extra->' . self::EXTRA_TAXONOMY_LABELS,
        'extra->' . self::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST
    ];

    protected $casts = [
        'extra' => 'array',
        'is_default' => 'boolean',
        'showsubcat' => 'boolean',
        'section_name' => 'json',
    ];

    const EXTRA_TAXONOMY_LABELS = 'taxonomy_labels';
    const SECTION_BROWSE = 'browse';
    const SECTION_SPECIAL = 'special';

    public static array $sections = [
        self::SECTION_BROWSE => ['text' => 'Browse'],
        self::SECTION_SPECIAL => ['text' => 'Special'],
    ];

    const EXTRA_DISPLAY_COVER_ON_TORRENT_LIST = 'display_cover_on_torrent_list';
    const EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST = 'display_seed_box_icon_on_torrent_list';

    public static array $taxonomies = [
        'source' => 'sources',
        'medium' => 'media',
        'codec' => 'codecs',
        'audiocodec' => 'audiocodecs',
        'standard' => 'standards',
        'processing' => 'processings',
        'team' => 'teams',
    ];

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

    public static function formatTaxonomyExtra(array $data): array
    {
        foreach (self::$taxonomies as $field => $table) {
            $data["show{$field}"] = 0;
            foreach ($data['extra'][self::EXTRA_TAXONOMY_LABELS] ?? [] as $item) {
                if ($field == $item['torrent_field']) {
                    $data["show{$field}"] = 1;
//                    $data["extra->" . self::EXTRA_TAXONOMY_LABELS][] = $item;
                }
            }
        }
        $data["extra->" . self::EXTRA_TAXONOMY_LABELS] = $data['extra'][self::EXTRA_TAXONOMY_LABELS];
        return $data;
    }

    public function getTaxonomyLabel($torrentField)
    {
        $lang = get_langfolder_cookie();
        foreach ($this->extra[self::EXTRA_TAXONOMY_LABELS] ?? [] as $item) {
            if ($item['torrent_field'] == $torrentField) {
                return $item['display_text'][$lang] ?? 'Unknown';
            }
        }
        return nexus_trans("searchbox.sub_category_{$torrentField}_label") ?: ucfirst($torrentField);
    }

    protected function customFields(): Attribute
    {
        return new Attribute(
            get: fn ($value) => is_string($value) ? explode(',', $value) : $value,
            set: fn ($value) => is_array($value) ? implode(',', $value) : $value,
        );
    }

    public static function getSubCatOptions(): array
    {
        return array_combine(array_keys(self::$taxonomies), array_keys(self::$taxonomies));
    }

    public static function listSections($field = null): array
    {
        $result = [];
        foreach (self::$sections as $key => $value) {
            $value['text'] = nexus_trans("searchbox.sections.$key");
            $value['mode'] = Setting::get("main.{$key}cat");
            if ($field !== null && isset($value[$field])) {
                $result[$key] = $value[$field];
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    public static function get(int $id)
    {
        if (!isset(self::$instances[$id])) {
            self::$instances[$id] = self::query()->find($id);
        }
        return self::$instances[$id];
    }

    public static function listTaxonomyItems($searchBox, $torrentField): \Illuminate\Support\Collection
    {
        if (!$searchBox instanceof self) {
            $searchBox = self::get(intval($searchBox));
        }
        $table = self::$taxonomies[$torrentField];
        return NexusDB::table($table)->where(function (Builder $query) use ($searchBox) {
            return $query->where('mode', $searchBox->id)->orWhere('mode', 0);
        })->get();
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

    public function taxonomy_source(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Source::class, 'mode');
    }

    public function taxonomy_medium(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Media::class, 'mode');
    }

    public function taxonomy_standard(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Standard::class, 'mode');
    }

    public function taxonomy_codec(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Codec::class, 'mode');
    }

    public function taxonomy_audiocodec(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AudioCodec::class, 'mode');
    }

    public function taxonomy_team(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Team::class, 'mode');
    }

    public function taxonomy_processing(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Processing::class, 'mode');
    }

    public function taxonomies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Taxonomy::class, 'mode');
    }


}
