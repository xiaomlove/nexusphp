<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

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

    public static array $subCatFields = [
        'source', 'medium', 'codec', 'audiocodec', 'team', 'standard', 'processing'
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

    public static function getTaxonomyDisplayText($field)
    {

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
        return array_combine(self::$subCatFields, self::$subCatFields);
    }

    public function categories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Category::class, 'mode');
    }

    public function normal_fields(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SearchBoxField::class, 'searchbox_id');
    }

    public function taxonomy_sources(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Source::class, 'mode');
    }

    public function taxonomy_medium(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Media::class, 'mode');
    }

    public function taxonomy_standards(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Standard::class, 'mode');
    }

    public function taxonomy_codecs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Codec::class, 'mode');
    }

    public function taxonomy_audio_codecs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AudioCodec::class, 'mode');
    }

    public function taxonomy_teams(): \Illuminate\Database\Eloquent\Relations\HasMany
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
