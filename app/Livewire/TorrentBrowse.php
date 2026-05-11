<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Tag;
use App\Models\Torrent;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
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
 * The legacy page kept very granular sub-category filters (sources
 * / media / codecs / standards / processings / teams / audiocodecs)
 * and numeric range filters (size / seeders / leechers / added).
 * These are intentionally **out of scope** for this spike — they
 * will land in follow-up Phase 3 PRs as separate, reviewable units.
 * The list of deferred filters is documented in the PR description.
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

            $url = '/torrents.php';
            if (! empty($query)) {
                $url .= '?'.http_build_query($query);
            }

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
