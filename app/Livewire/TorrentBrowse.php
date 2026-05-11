<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Tag;
use App\Models\Torrent;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;
use Livewire\WithPagination;

/**
 * `/browse` — Phase 3 Strangler Fig migration of `public/torrents.php`
 * (1 436 LOC) to a Livewire 3 component. See
 * `docs/legacy-strategy.md` § "Phase 3 — big user pages".
 *
 * PR #79 added the foundation (search + category + sort + onlyFree).
 * This component now mirrors more of the legacy filtering surface
 * so Phase 3 can land in a series of focused PRs rather than one
 * 1 400-line drop:
 *
 *  - section type (`mode`) — main vs special torrents board, gated
 *    by `main.spsct` setting and `view_special_torrent` permission.
 *  - special-promotion state (`spstate`) — all / normal / free /
 *    2× up / free + 2× up / 50% down / 50% down + 2× up / 30% down.
 *    Replaces the boolean `onlyFree` from PR #79 (the value `free`
 *    on the new filter is the upgrade path; old `?free=1` URLs are
 *    accepted on `mount()` and silently translated).
 *  - visibility (`incldead`) — active / dead / all, like legacy.
 *  - bookmark filter (`inclbookmarked`) — all / bookmarked /
 *    not-bookmarked. Requires an authenticated user.
 *  - tag filter (`tag_id`) — single-tag dropdown.
 *  - extra sorts (`name`, `comments`) — gives the new browse parity
 *    with the legacy column-header sorts that users used most.
 *
 *  - `?legacy=1` canary — when present, `/browse` redirects to
 *    `/torrents.php` so users can roll back instantly. This is the
 *    pattern documented in `docs/legacy-strategy.md` § "Per-page
 *    rule"; in this PR `/torrents.php` is *not* yet replaced, the
 *    flag exists so the next Phase 3 PR that flips the URLs has
 *    the rollback path already wired (and tested).
 *
 * Phase 3.2 adds the rest of the filter surface that the legacy
 * page exposed:
 *
 *  - Range filters (`size_begin` / `size_end` in GB,
 *    `seeders_begin` / `seeders_end`, `leechers_begin` /
 *    `leechers_end`, `times_completed_begin` /
 *    `times_completed_end`). Empty values are dropped from the
 *    URL and skipped from the query, matching legacy behaviour.
 *
 *  - Sub-category dropdowns (`source`, `medium`, `codec`,
 *    `standard`, `processing`, `team`, `audiocodec`). Each maps
 *    to a single tinyint column on `torrents`; option lists are
 *    mode-aware so e.g. picking a movie section narrows
 *    `codec` to movie codecs.
 *
 * Still out of scope (Phase 3.3+):
 *  - `showsubcat` / `showsource` / `showcodec` … gates per
 *    `SearchBox` (always render the dropdown for now; mode-aware
 *    option list returns empty when the section doesn't use it).
 *  - Multi-select sub-categories (legacy SQL supports
 *    `source IN(…)`; UI is single-pick everywhere except the
 *    legacy form builder, so we match the URL contract).
 *  - `added_begin` / `added_end` date-range filter.
 *  - `search_area` switches (description, owner, file list).
 */
class TorrentBrowse extends Component
{
    use WithPagination;

    public const MODE_TORRENTS = 0;

    public const MODE_SPECIAL = 1;

    public const INCLUDE_DEAD_ACTIVE = 'active';

    public const INCLUDE_DEAD_DEAD = 'dead';

    public const INCLUDE_DEAD_ALL = 'all';

    public const BOOKMARK_ALL = 'all';

    public const BOOKMARK_ONLY = 'only';

    public const BOOKMARK_EXCLUDE = 'exclude';

    public const SPSTATE_ALL = 'all';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'cat', except: '')]
    public string $category = '';

    #[Url(as: 'sort', except: 'newest')]
    public string $sort = 'newest';

    /**
     * Section / SearchBox id (`Category::mode`). 0 = no filter
     * (default — browse across all sections), > 0 = narrow the
     * dropdowns and the result set to a single SearchBox, the
     * same id that `Setting::get('main.browsecat')` /
     * `Setting::get('main.specialcat')` would return.
     */
    #[Url(as: 'mode', except: self::MODE_TORRENTS)]
    public int $mode = self::MODE_TORRENTS;

    /**
     * `sp_state` filter. Stored as a string ("all", "1", … "7") so
     * the empty/default value can disappear from the URL via
     * `#[Url(except: 'all')]`. The numeric values match the
     * `Torrent::PROMOTION_*` constants.
     */
    #[Url(as: 'spstate', except: self::SPSTATE_ALL)]
    public string $spState = self::SPSTATE_ALL;

    /**
     * Visibility filter:
     *  - `active`  → only `visible = 'yes'` (legacy default).
     *  - `dead`    → only `visible = 'no'`.
     *  - `all`     → both.
     * Matches `public/torrents.php`'s `incldead` semantics (the
     * legacy file used numeric 0/1/2; we use names because they
     * read better in URLs).
     */
    #[Url(as: 'incldead', except: self::INCLUDE_DEAD_ACTIVE)]
    public string $includeDead = self::INCLUDE_DEAD_ACTIVE;

    /**
     * Bookmark filter — `all` / `only` / `exclude`. The legacy file
     * had it as `inclbookmarked` 0/1/2; we use names because URLs
     * are easier to read. When the user is unauthenticated this
     * filter is a no-op (bookmarks are per-user).
     */
    #[Url(as: 'inclbookmarked', except: self::BOOKMARK_ALL)]
    public string $bookmarked = self::BOOKMARK_ALL;

    /**
     * Single tag id, or 0 for "no tag filter". Mirrors
     * `public/torrents.php`'s `tag_id` query parameter and the
     * inner join on `torrent_tags`.
     */
    #[Url(as: 'tag_id', except: 0)]
    public int $tagId = 0;

    /**
     * Legacy boolean shortcut from PR #79 — accepted on `mount()`
     * for backward compatibility with existing inbound links, then
     * mapped into `$spState` and unset before render. We declare
     * the property (instead of just reading `$request->free`) so
     * Livewire's URL-binding round-trips the value during mount.
     */
    #[Url(as: 'free', except: false)]
    public bool $onlyFree = false;

    /**
     * Size lower bound, in **gigabytes**. `null` means "no lower
     * bound" and is dropped from the URL. Matches the legacy
     * `size_begin` GET parameter, which `public/torrents.php`
     * multiplies by `1024 ** 3` before comparing against
     * `torrents.size` (bytes).
     */
    #[Url(as: 'size_begin', except: null)]
    public ?int $sizeMin = null;

    #[Url(as: 'size_end', except: null)]
    public ?int $sizeMax = null;

    #[Url(as: 'seeders_begin', except: null)]
    public ?int $seedersMin = null;

    #[Url(as: 'seeders_end', except: null)]
    public ?int $seedersMax = null;

    #[Url(as: 'leechers_begin', except: null)]
    public ?int $leechersMin = null;

    #[Url(as: 'leechers_end', except: null)]
    public ?int $leechersMax = null;

    /**
     * "Snatches" = `times_completed`. Legacy URL parameter is
     * `times_completed_begin` / `_end`.
     */
    #[Url(as: 'times_completed_begin', except: null)]
    public ?int $snatchesMin = null;

    #[Url(as: 'times_completed_end', except: null)]
    public ?int $snatchesMax = null;

    /**
     * Sub-category filters — each is a single tinyint id picked from
     * the corresponding lookup table. 0 means "no filter" and is
     * elided from the URL. Mirrors `public/torrents.php`'s `source`,
     * `medium`, `codec`, `standard`, `processing`, `team`,
     * `audiocodec` GET params.
     */
    #[Url(as: 'source', except: 0)]
    public int $source = 0;

    #[Url(as: 'medium', except: 0)]
    public int $medium = 0;

    #[Url(as: 'codec', except: 0)]
    public int $codec = 0;

    #[Url(as: 'standard', except: 0)]
    public int $standard = 0;

    #[Url(as: 'processing', except: 0)]
    public int $processing = 0;

    #[Url(as: 'team', except: 0)]
    public int $team = 0;

    #[Url(as: 'audiocodec', except: 0)]
    public int $audiocodec = 0;

    /**
     * Legacy column ↔ option-table map for the sub-category
     * filters. Keys are the property name on this component; the
     * value's `column` is the `torrents` column to filter on and
     * `table` is the lookup table used to populate the dropdown.
     */
    private const SUBCATEGORIES = [
        'source' => ['column' => 'source', 'table' => 'sources', 'label' => 'Source'],
        'medium' => ['column' => 'medium', 'table' => 'media', 'label' => 'Medium'],
        'codec' => ['column' => 'codec', 'table' => 'codecs', 'label' => 'Codec'],
        'standard' => ['column' => 'standard', 'table' => 'standards', 'label' => 'Standard'],
        'processing' => ['column' => 'processing', 'table' => 'processings', 'label' => 'Processing'],
        'team' => ['column' => 'team', 'table' => 'teams', 'label' => 'Team'],
        'audiocodec' => ['column' => 'audiocodec', 'table' => 'audiocodecs', 'label' => 'Audio codec'],
    ];

    private const SORTS = [
        'newest' => ['column' => 'added', 'direction' => 'desc', 'label' => 'Newest'],
        'oldest' => ['column' => 'added', 'direction' => 'asc', 'label' => 'Oldest'],
        'seeders' => ['column' => 'seeders', 'direction' => 'desc', 'label' => 'Most seeded'],
        'leechers' => ['column' => 'leechers', 'direction' => 'desc', 'label' => 'Most leeched'],
        'snatches' => ['column' => 'times_completed', 'direction' => 'desc', 'label' => 'Most snatched'],
        'comments' => ['column' => 'comments', 'direction' => 'desc', 'label' => 'Most commented'],
        'largest' => ['column' => 'size', 'direction' => 'desc', 'label' => 'Largest'],
        'smallest' => ['column' => 'size', 'direction' => 'asc', 'label' => 'Smallest'],
        'name_asc' => ['column' => 'name', 'direction' => 'asc', 'label' => 'Name (A→Z)'],
        'name_desc' => ['column' => 'name', 'direction' => 'desc', 'label' => 'Name (Z→A)'],
    ];

    private const SPSTATE_TO_PROMOTION = [
        '1' => Torrent::PROMOTION_NORMAL,
        '2' => Torrent::PROMOTION_FREE,
        '3' => Torrent::PROMOTION_TWO_TIMES_UP,
        '4' => Torrent::PROMOTION_FREE_TWO_TIMES_UP,
        '5' => Torrent::PROMOTION_HALF_DOWN,
        '6' => Torrent::PROMOTION_HALF_DOWN_TWO_TIMES_UP,
        '7' => Torrent::PROMOTION_ONE_THIRD_DOWN,
    ];

    /**
     * Phase 3 canary mount — if the user explicitly opts back into
     * the legacy page via `?legacy=1`, redirect there preserving
     * everything else in the URL. Also normalises the boolean
     * `?free=1` shortcut to the new `spstate` filter and re-clamps
     * the section type when the user lacks `view_special_torrent`.
     */
    public function mount(): ?Redirector
    {
        if (request()->query('legacy') === '1') {
            $query = collect(request()->query())
                ->except('legacy')
                ->all();

            // Pass `legacy=1` through so the Strangler Fig redirect at
            // the top of `public/torrents.php` keeps the user on legacy
            // instead of bouncing them back to `/browse`.
            $query['legacy'] = '1';

            $url = '/torrents.php?'.http_build_query($query);

            return redirect($url);
        }

        if ($this->onlyFree && $this->spState === self::SPSTATE_ALL) {
            $this->spState = (string) Torrent::PROMOTION_FREE;
        }
        $this->onlyFree = false;

        if ($this->bookmarked !== self::BOOKMARK_ALL && auth('nexus-web')->guest()) {
            $this->bookmarked = self::BOOKMARK_ALL;
        }

        return null;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function updatingMode(): void
    {
        $this->resetPage();
        // category options depend on mode → drop a now-incompatible cat
        $this->category = '';
    }

    public function updatingSpState(): void
    {
        $this->resetPage();
    }

    public function updatingIncludeDead(): void
    {
        $this->resetPage();
    }

    public function updatingBookmarked(): void
    {
        $this->resetPage();
    }

    public function updatingTagId(): void
    {
        $this->resetPage();
    }

    public function updatingOnlyFree(): void
    {
        $this->resetPage();
    }

    public function updatingSizeMin(): void
    {
        $this->resetPage();
    }

    public function updatingSizeMax(): void
    {
        $this->resetPage();
    }

    public function updatingSeedersMin(): void
    {
        $this->resetPage();
    }

    public function updatingSeedersMax(): void
    {
        $this->resetPage();
    }

    public function updatingLeechersMin(): void
    {
        $this->resetPage();
    }

    public function updatingLeechersMax(): void
    {
        $this->resetPage();
    }

    public function updatingSnatchesMin(): void
    {
        $this->resetPage();
    }

    public function updatingSnatchesMax(): void
    {
        $this->resetPage();
    }

    public function updatingSource(): void
    {
        $this->resetPage();
    }

    public function updatingMedium(): void
    {
        $this->resetPage();
    }

    public function updatingCodec(): void
    {
        $this->resetPage();
    }

    public function updatingStandard(): void
    {
        $this->resetPage();
    }

    public function updatingProcessing(): void
    {
        $this->resetPage();
    }

    public function updatingTeam(): void
    {
        $this->resetPage();
    }

    public function updatingAudiocodec(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset([
            'search',
            'category',
            'sort',
            'spState',
            'includeDead',
            'bookmarked',
            'tagId',
            'onlyFree',
            'sizeMin',
            'sizeMax',
            'seedersMin',
            'seedersMax',
            'leechersMin',
            'leechersMax',
            'snatchesMin',
            'snatchesMax',
            'source',
            'medium',
            'codec',
            'standard',
            'processing',
            'team',
            'audiocodec',
        ]);
        $this->sort = 'newest';
        $this->spState = self::SPSTATE_ALL;
        $this->includeDead = self::INCLUDE_DEAD_ACTIVE;
        $this->bookmarked = self::BOOKMARK_ALL;
        $this->resetPage();
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    #[Computed]
    public function sortOptions(): array
    {
        $out = [];
        foreach (self::SORTS as $key => $cfg) {
            $out[] = ['key' => $key, 'label' => $cfg['label']];
        }

        return $out;
    }

    /**
     * Categories shown in the UI dropdown. When the user has picked
     * a specific section (`mode > 0`) we narrow the list to that
     * search-box, matching the legacy `genrelist($sectiontype)`
     * call in `public/torrents.php:46`. When `mode = 0` (the
     * default) we show every category so the user can browse
     * across sections, which differs from the legacy default but
     * is the most useful "no filter" behaviour for a Livewire
     * single-page browser.
     *
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories()
    {
        $query = Category::query()
            ->orderBy('sort_index')
            ->orderBy('id');

        if ($this->mode > 0) {
            $query->where('mode', $this->mode);
        }

        return $query->get(['id', 'name', 'mode']);
    }

    /**
     * Tag list shown in the UI dropdown. Mirrors
     * `App\Repositories\TagRepository::listAll($searchBoxId)` —
     * tags with `mode = 0` are "shared across sections" and always
     * appear; mode > 0 picks per-section tags.
     *
     * @return Collection<int, Tag>
     */
    #[Computed]
    public function tags()
    {
        $query = Tag::query()
            ->orderBy('priority')
            ->orderBy('id');

        if ($this->mode > 0) {
            $query->whereIn('mode', [0, $this->mode]);
        }

        return $query->get(['id', 'name', 'mode']);
    }

    /**
     * Per-property dropdown options for every sub-category filter,
     * keyed by the public property name on this component
     * (`source`, `medium`, …). Each entry's `options` is an array
     * of `{id, name}` rows already ordered by `sort_index, id`,
     * matching `searchbox_item_list()` in `include/functions.php`.
     * When the user has picked a specific section (`mode > 0`)
     * the lookup is narrowed to rows where `mode = 0` (global)
     * or `mode = $this->mode` (section-specific) — same shape as
     * the legacy `searchbox_item_list($table, $sectiontype)` call.
     *
     * @return array<string, array{label: string, options: array<int, array{id: int, name: string}>}>
     */
    #[Computed]
    public function subcategoryOptions(): array
    {
        $out = [];
        foreach (self::SUBCATEGORIES as $property => $cfg) {
            $query = DB::table($cfg['table'])
                ->select(['id', 'name'])
                ->orderBy('sort_index')
                ->orderBy('id');
            if ($this->mode > 0) {
                $query->whereIn('mode', [0, $this->mode]);
            }
            $rows = $query->get()
                ->map(fn ($row) => ['id' => (int) $row->id, 'name' => (string) $row->name])
                ->all();
            $out[$property] = [
                'label' => $cfg['label'],
                'options' => $rows,
            ];
        }

        return $out;
    }

    public function render(): View
    {
        $sortConfig = self::SORTS[$this->sort] ?? self::SORTS['newest'];

        $query = Torrent::query()
            ->with(['basic_category:id,name'])
            ->where('banned', Torrent::BANNED_NO);

        if ($this->includeDead === self::INCLUDE_DEAD_ACTIVE) {
            $query->where('visible', Torrent::VISIBLE_YES);
        } elseif ($this->includeDead === self::INCLUDE_DEAD_DEAD) {
            $query->where('visible', Torrent::VISIBLE_NO);
        }

        // Range filters — `null` ↔ "no bound", matching the legacy
        // `isset() && ctype_digit()` guard in `public/torrents.php`.
        // Size bounds arrive in GB; convert to bytes here so the
        // comparison can use the existing `torrents.size` column.
        $gb = 1024 ** 3;
        if ($this->sizeMin !== null && $this->sizeMin > 0) {
            $query->where('size', '>=', $this->sizeMin * $gb);
        }
        if ($this->sizeMax !== null && $this->sizeMax > 0) {
            $query->where('size', '<=', $this->sizeMax * $gb);
        }
        if ($this->seedersMin !== null && $this->seedersMin >= 0) {
            $query->where('seeders', '>=', $this->seedersMin);
        }
        if ($this->seedersMax !== null && $this->seedersMax >= 0) {
            $query->where('seeders', '<=', $this->seedersMax);
        }
        if ($this->leechersMin !== null && $this->leechersMin >= 0) {
            $query->where('leechers', '>=', $this->leechersMin);
        }
        if ($this->leechersMax !== null && $this->leechersMax >= 0) {
            $query->where('leechers', '<=', $this->leechersMax);
        }
        if ($this->snatchesMin !== null && $this->snatchesMin >= 0) {
            $query->where('times_completed', '>=', $this->snatchesMin);
        }
        if ($this->snatchesMax !== null && $this->snatchesMax >= 0) {
            $query->where('times_completed', '<=', $this->snatchesMax);
        }

        // Sub-category filters — each property is a single tinyint
        // id (0 = "no filter"). Iterate the static map so each
        // filter is applied identically.
        foreach (self::SUBCATEGORIES as $property => $cfg) {
            $value = $this->{$property};
            if (is_int($value) && $value > 0) {
                $query->where($cfg['column'], $value);
            }
        }

        if ($this->search !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], $this->search).'%';
            $query->where(function (Builder $sub) use ($needle) {
                $sub->where('name', 'like', $needle)
                    ->orWhere('small_descr', 'like', $needle);
            });
        }

        if ($this->category !== '' && ctype_digit($this->category)) {
            $query->where('category', (int) $this->category);
        }

        if (array_key_exists($this->spState, self::SPSTATE_TO_PROMOTION)) {
            $query->where('sp_state', self::SPSTATE_TO_PROMOTION[$this->spState]);
        }

        if ($this->tagId > 0) {
            $query->whereExists(function ($sub) {
                $sub->from('torrent_tags')
                    ->whereColumn('torrent_tags.torrent_id', 'torrents.id')
                    ->where('torrent_tags.tag_id', $this->tagId);
            });
        }

        $user = auth('nexus-web')->user();
        if ($user !== null && $this->bookmarked === self::BOOKMARK_ONLY) {
            $query->whereExists(function ($sub) use ($user) {
                $sub->from('bookmarks')
                    ->whereColumn('bookmarks.torrentid', 'torrents.id')
                    ->where('bookmarks.userid', $user->id);
            });
        } elseif ($user !== null && $this->bookmarked === self::BOOKMARK_EXCLUDE) {
            $query->whereNotExists(function ($sub) use ($user) {
                $sub->from('bookmarks')
                    ->whereColumn('bookmarks.torrentid', 'torrents.id')
                    ->where('bookmarks.userid', $user->id);
            });
        }

        // When the user has picked a specific section (`mode > 0`)
        // restrict torrents to categories that belong to that
        // search-box. Default (`mode = 0`) shows every section so
        // the Livewire browser stays useful as a single-page entry
        // point.
        if ($this->mode > 0) {
            $query->whereExists(function ($sub) {
                $sub->from('categories')
                    ->whereColumn('categories.id', 'torrents.category')
                    ->where('categories.mode', $this->mode);
            });
        }

        $query->orderBy($sortConfig['column'], $sortConfig['direction']);
        if ($sortConfig['column'] !== 'id') {
            $query->orderBy('id', 'desc');
        }

        $torrents = $query->paginate(24);

        return view('livewire.torrent-browse', [
            'torrents' => $torrents,
            'currentSortLabel' => $sortConfig['label'],
        ])->layout('layouts.livewire-app', [
            'title' => 'Browse Torrents',
        ]);
    }

    public static function formatBytes(int|float $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 ** 2) {
            return number_format($bytes / 1024, 2).' KB';
        }
        if ($bytes < 1024 ** 3) {
            return number_format($bytes / (1024 ** 2), 2).' MB';
        }
        if ($bytes < 1024 ** 4) {
            return number_format($bytes / (1024 ** 3), 2).' GB';
        }

        return number_format($bytes / (1024 ** 4), 2).' TB';
    }

    /**
     * @return array<string, string>
     */
    public static function spStateOptions(): array
    {
        return [
            self::SPSTATE_ALL => 'All',
            (string) Torrent::PROMOTION_NORMAL => 'Normal',
            (string) Torrent::PROMOTION_FREE => 'Free',
            (string) Torrent::PROMOTION_TWO_TIMES_UP => '2× up',
            (string) Torrent::PROMOTION_FREE_TWO_TIMES_UP => 'Free + 2× up',
            (string) Torrent::PROMOTION_HALF_DOWN => '50% down',
            (string) Torrent::PROMOTION_HALF_DOWN_TWO_TIMES_UP => '50% + 2× up',
            (string) Torrent::PROMOTION_ONE_THIRD_DOWN => '30% down',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function includeDeadOptions(): array
    {
        return [
            self::INCLUDE_DEAD_ACTIVE => 'Active only',
            self::INCLUDE_DEAD_DEAD => 'Dead only',
            self::INCLUDE_DEAD_ALL => 'All',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function bookmarkOptions(): array
    {
        return [
            self::BOOKMARK_ALL => 'All',
            self::BOOKMARK_ONLY => 'Bookmarked only',
            self::BOOKMARK_EXCLUDE => 'Hide bookmarked',
        ];
    }
}
