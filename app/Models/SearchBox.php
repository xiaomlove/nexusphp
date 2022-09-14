<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

class SearchBox extends NexusModel
{
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
    ];

    const EXTRA_TAXONOMY_LABELS = 'taxonomy_labels';

    const EXTRA_DISPLAY_COVER_ON_TORRENT_LIST = 'display_cover_on_torrent_list';
    const EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST = 'display_seed_box_icon_on_torrent_list';

    public static array $taxonomies = [
        'source' => 'sources',
        'medium' => 'media',
        'codec' => 'codecs',
        'audiocodec' => 'audiocodecs',
        'team' => 'teams',
        'standard' => 'standards',
        'processing' => 'processings'
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
                    $data["extra->" . self::EXTRA_TAXONOMY_LABELS][] = $item;
                }
            }
        }
        return $data;
    }

    public function getTaxonomyLabel($torrentField)
    {
        foreach ($this->extra[self::EXTRA_TAXONOMY_LABELS] ?? [] as $item) {
            if ($item['torrent_field'] == $torrentField) {
                return $item['display_text'];
            }
        }
        return nexus_trans('label.torrent.' . $torrentField) ?: ucfirst($torrentField);
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
