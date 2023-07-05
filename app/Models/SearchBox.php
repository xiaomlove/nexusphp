<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Nexus\Database\NexusDB;

class SearchBox extends NexusModel
{
    private static array $instances = [];

    private static array $modeOptions = [];

    protected $table = 'searchbox';

    protected $fillable = [
        'name', 'catsperrow', 'catpadding', 'showsubcat', 'section_name', 'is_default',
        'showsource', 'showmedium', 'showcodec', 'showstandard', 'showprocessing', 'showteam', 'showaudiocodec',
        'custom_fields', 'custom_fields_display_name', 'custom_fields_display',
        'extra->' . self::EXTRA_TAXONOMY_LABELS,
        'extra->' . self::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST,
        'extra->' . self::EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST,
    ];

    protected $casts = [
        'extra' => 'array',
        'is_default' => 'boolean',
        'showsubcat' => 'boolean',
        'section_name' => 'json',
    ];

    const SEARCH_MODE_AND = '0';
    const SEARCH_MODE_EXACT = '2';

    public static array $searchModes = [
        self::SEARCH_MODE_AND => ['text' => 'and'],
        self::SEARCH_MODE_EXACT => ['text' => 'exact'],
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
        'source' => ['table' => 'sources', 'model' => Source::class],
        'medium' => ['table' => 'media', 'model' => Media::class],
        'codec' => ['table' => 'codecs', 'model' => Codec::class],
        'audiocodec' => ['table' => 'audiocodecs', 'model' => AudioCodec::class],
        'standard' => ['table' => 'standards', 'model' => Standard::class],
        'processing' => ['table' => 'processings', 'model' => Processing::class],
        'team' => ['table' => 'teams', 'model' => Team::class]
    ];

    public static array $extras = [
        self::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST => ['text' => 'Display cover on torrent list'],
        self::EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST => ['text' => 'Display seed box icon on torrent list'],
    ];

    public static function listExtraText($fullName = false): array
    {
        $result = [];
        foreach (self::$extras as $field => $info) {
            if ($fullName) {
                $name = "extra[$field]";
            } else {
                $name = $field;
            }
            $result[$name] = nexus_trans("searchbox.extras.$field");
        }
        return $result;
    }

    public static function formatTaxonomyExtra(array $data): array
    {
        do_log("data: " . json_encode($data));
        foreach (self::$taxonomies as $field => $table) {
            $data["show{$field}"] = 0;
            foreach ($data['extra'][self::EXTRA_TAXONOMY_LABELS] ?? [] as $item) {
                if ($field == $item['torrent_field']) {
                    $data["show{$field}"] = 1;
                }
            }
        }
        $data["extra->" . self::EXTRA_TAXONOMY_LABELS] = $data['extra'][self::EXTRA_TAXONOMY_LABELS];
        $other = $data['other'] ?? [];
        $data["extra->" . self::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST] = in_array(self::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST, $other) ? 1 : 0;
        $data["extra->" . self::EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST] = in_array(self::EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST, $other) ? 1 : 0;
        $data['custom_fields'] = array_filter($data['custom_fields']);
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
        $table = self::$taxonomies[$torrentField]['table'];
        return NexusDB::table($table)->where(function (Builder $query) use ($searchBox) {
            return $query->where('mode', $searchBox->id)->orWhere('mode', 0);
        })->orderBy('sort_index')->orderBy('id')->get();
    }

    public static function listModeOptions(): array
    {
        if (!empty(self::$modeOptions)) {
            return self::$modeOptions;
        }
        self::$modeOptions = SearchBox::query()
            ->pluck('name', 'id')
            ->toArray();
        return self::$modeOptions;
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

    public static function listSearchModes(): array
    {
        $result = [];
        foreach (self::$searchModes as $key => $value) {
            $result[$key] = nexus_trans("search.search_modes.{$value['text']}");
        }
        return $result;
    }

    public static function isSpecialEnabled(): bool
    {
        return Setting::get('main.spsct') == 'yes';
    }

    public static function getBrowseMode()
    {
        return Setting::get('main.browsecat');
    }

    public static function getSpecialMode()
    {
        return Setting::get('main.specialcat');
    }


    public function categories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Category::class, 'mode');
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

    public static function getDefaultSearchMode()
    {
        $meiliConf = get_setting("meilisearch");
        if ($meiliConf['enabled'] == 'yes') {
            return $meiliConf['default_search_mode'];
        } else {
            return self::SEARCH_MODE_AND;
        }
    }

    public static function listSelectModeOptions($selectedValue): string
    {
        $options = [];
        if (!is_numeric($selectedValue)) {
            //set default
            $selectedValue = self::getDefaultSearchMode();
        }
        foreach (self::listSearchModes() as $key => $text) {
            $selected = "";
            if ((string)$key === (string)$selectedValue) {
                $selected = " selected";
            }
            $options[] = sprintf('<option value="%s"%s>%s</option>', $key, $selected, $text);
        }
        return implode('', $options);
    }

    public static function listCategoryId($searchBoxId, $glue = null): array|string|null
    {
        static $results = null;
        if (is_null($results)) {
            $results = [];
            $res = genrelist($searchBoxId);
            foreach ($res as $item) {
                $results[] = $item['id'];
            }
        }
        if (!is_null($glue)) {
            $results = implode($glue, $results);
        }
        return $results;
    }


}
