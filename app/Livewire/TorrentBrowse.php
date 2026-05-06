<?php

namespace App\Livewire;

use App\Models\AudioCodec;
use App\Models\Category;
use App\Models\Codec;
use App\Models\Media;
use App\Models\Processing;
use App\Models\Setting;
use App\Models\Source;
use App\Models\Standard;
use App\Models\Team;
use App\Models\Torrent;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Nexus\Imdb\Imdb;

class TorrentBrowse extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'cat', except: '')]
    public string $category = '';

    #[Url(as: 'sort', except: 'newest')]
    public string $sort = 'newest';

    #[Url(as: 'free', except: false)]
    public bool $onlyFree = false;

    /** @var array<int, int> */
    #[Url(as: 'src', except: [])]
    public array $sources = [];

    /** @var array<int, int> */
    #[Url(as: 'med', except: [])]
    public array $media = [];

    /** @var array<int, int> */
    #[Url(as: 'cdc', except: [])]
    public array $codecs = [];

    /** @var array<int, int> */
    #[Url(as: 'std', except: [])]
    public array $standards = [];

    /** @var array<int, int> */
    #[Url(as: 'pro', except: [])]
    public array $processings = [];

    /** @var array<int, int> */
    #[Url(as: 'tm', except: [])]
    public array $teams = [];

    /** @var array<int, int> */
    #[Url(as: 'aud', except: [])]
    public array $audiocodecs = [];

    public int $perPage = 24;

    private const PAGE_SIZE = 24;

    private const SORTS = [
        'newest' => ['column' => 'added', 'direction' => 'desc', 'label' => 'Newest'],
        'oldest' => ['column' => 'added', 'direction' => 'asc', 'label' => 'Oldest'],
        'seeders' => ['column' => 'seeders', 'direction' => 'desc', 'label' => 'Most seeded'],
        'leechers' => ['column' => 'leechers', 'direction' => 'desc', 'label' => 'Most leeched'],
        'snatches' => ['column' => 'times_completed', 'direction' => 'desc', 'label' => 'Most snatched'],
        'largest' => ['column' => 'size', 'direction' => 'desc', 'label' => 'Largest'],
        'smallest' => ['column' => 'size', 'direction' => 'asc', 'label' => 'Smallest'],
    ];

    /**
     * Map of facet name → [model class, torrent column].
     * Mirrors the columns on the torrents table that take an int FK
     * into each subcategory lookup table.
     */
    private const FACETS = [
        'sources' => [Source::class, 'source'],
        'media' => [Media::class, 'medium'],
        'codecs' => [Codec::class, 'codec'],
        'standards' => [Standard::class, 'standard'],
        'processings' => [Processing::class, 'processing'],
        'teams' => [Team::class, 'team'],
        'audiocodecs' => [AudioCodec::class, 'audiocodec'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPaging();
    }

    public function updatingCategory(): void
    {
        $this->reset(['sources', 'media', 'codecs', 'standards', 'processings', 'teams', 'audiocodecs']);
        $this->resetPaging();
    }

    public function updatingSort(): void
    {
        $this->resetPaging();
    }

    public function updatingOnlyFree(): void
    {
        $this->resetPaging();
    }

    public function updatingSources(): void
    {
        $this->resetPaging();
    }

    public function updatingMedia(): void
    {
        $this->resetPaging();
    }

    public function updatingCodecs(): void
    {
        $this->resetPaging();
    }

    public function updatingStandards(): void
    {
        $this->resetPaging();
    }

    public function updatingProcessings(): void
    {
        $this->resetPaging();
    }

    public function updatingTeams(): void
    {
        $this->resetPaging();
    }

    public function updatingAudiocodecs(): void
    {
        $this->resetPaging();
    }

    public function loadMore(): void
    {
        $this->perPage += self::PAGE_SIZE;
    }

    public function clearFilters(): void
    {
        $this->reset([
            'search', 'category', 'sort', 'onlyFree',
            'sources', 'media', 'codecs', 'standards',
            'processings', 'teams', 'audiocodecs',
        ]);
        $this->sort = 'newest';
        $this->resetPaging();
    }

    /**
     * Reverb listener: when a torrent's promotion (sp_state) is changed
     * by an admin via setSpState() OR a global freeleech action is taken,
     * re-render the grid so the badge on the affected card (or all cards
     * for the global case) reflects the new state without a page reload.
     *
     * The method body is intentionally empty: any Livewire listener
     * invocation triggers a re-render, which re-runs render() and
     * re-evaluates each card's promotion badge from the freshly-fetched
     * Torrent rows. No diff is required.
     *
     * @param  array<string, mixed>  $payload
     */
    #[On('echo:torrents.promotion,TorrentPromotionChanged')]
    public function refreshOnPromotionChange(array $payload = []): void
    {
        // No-op — Livewire will re-render automatically.
    }

    private function resetPaging(): void
    {
        $this->perPage = self::PAGE_SIZE;
    }

    public function hasActiveFacets(): bool
    {
        foreach (array_keys(self::FACETS) as $property) {
            if (! empty($this->{$property})) {
                return true;
            }
        }

        return false;
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
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories()
    {
        return Category::query()
            ->orderBy('sort_index')
            ->orderBy('id')
            ->get(['id', 'name', 'mode']);
    }

    /**
     * Returns the list of facets to render and the rows for each. A
     * facet is only emitted when there's at least one matching row
     * (either universal mode=0 or matching the currently picked category).
     *
     * @return array<int, array{property: string, label: string, rows: \Illuminate\Support\Collection<int, object>}>
     */
    #[Computed]
    public function facets(): array
    {
        $mode = ($this->category !== '' && ctype_digit($this->category)) ? (int) $this->category : 0;
        $labels = [
            'sources' => 'Source',
            'media' => 'Medium',
            'codecs' => 'Codec',
            'standards' => 'Standard',
            'processings' => 'Processing',
            'teams' => 'Team',
            'audiocodecs' => 'Audio codec',
        ];

        $out = [];
        foreach (self::FACETS as $key => [$modelClass, $column]) {
            unset($column);
            $rows = $modelClass::query()
                ->when($mode > 0, fn ($q) => $q->where(fn ($w) => $w->where('mode', $mode)->orWhere('mode', 0)))
                ->orderBy('sort_index')
                ->orderBy('id')
                ->get(['id', 'name']);
            if ($rows->isEmpty()) {
                continue;
            }
            $out[] = [
                'property' => $key,
                'label' => $labels[$key],
                'rows' => $rows,
            ];
        }

        return $out;
    }

    public function render(): View
    {
        $sortConfig = self::SORTS[$this->sort] ?? self::SORTS['newest'];

        $base = $this->buildQuery();

        $total = (clone $base)->count();
        $items = (clone $base)
            ->orderBy($sortConfig['column'], $sortConfig['direction']);

        if ($sortConfig['column'] !== 'id') {
            $items->orderBy('id', 'desc');
        }

        $torrents = $items->take($this->perPage)->get();
        $covers = $this->resolveCovers($torrents);

        return view('livewire.torrent-browse', [
            'torrents' => $torrents,
            'covers' => $covers,
            'total' => $total,
            'hasMore' => $torrents->count() < $total,
            'currentSortLabel' => $sortConfig['label'],
        ])->layout('layouts.livewire-app', [
            'title' => 'Browse Torrents',
        ]);
    }

    /**
     * Build a map of torrent.id → poster URL string.
     *
     * Resolution order, mirroring legacy `torrents.php`:
     * 1. `torrents.cover` column if non-empty (admin/uploader override)
     * 2. IMDB cover via `Imdb::getMovieCover(parse_imdb_id(torrents.url))`
     *    when the IMDB integration is enabled (cached 10d in Redis).
     * 3. Empty string → card renders the placeholder.
     *
     * @param  Collection<int, Torrent>  $torrents
     * @return array<int, string>
     */
    private function resolveCovers($torrents): array
    {
        $covers = [];
        $imdb = null;
        $imdbEnabled = null;
        foreach ($torrents as $torrent) {
            $cover = (string) ($torrent->cover ?? '');
            if ($cover !== '') {
                $covers[(int) $torrent->id] = $cover;

                continue;
            }
            $url = (string) ($torrent->url ?? '');
            if ($url === '') {
                $covers[(int) $torrent->id] = '';

                continue;
            }
            if ($imdbEnabled === null) {
                $imdbEnabled = Setting::getIsImdbEnabled();
            }
            if (! $imdbEnabled) {
                $covers[(int) $torrent->id] = '';

                continue;
            }
            $imdbId = parse_imdb_id($url);
            if (! $imdbId) {
                $covers[(int) $torrent->id] = '';

                continue;
            }
            try {
                $imdb ??= new Imdb;
                $covers[(int) $torrent->id] = (string) $imdb->getMovieCover($imdbId);
            } catch (\Throwable $e) {
                do_log('imdb cover lookup failed: '.$e->getMessage(), 'error');
                $covers[(int) $torrent->id] = '';
            }
        }

        return $covers;
    }

    private function buildQuery(): Builder
    {
        $query = Torrent::query()
            ->with(['basic_category:id,name'])
            ->where('visible', Torrent::VISIBLE_YES)
            ->where('banned', Torrent::BANNED_NO);

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

        if ($this->onlyFree) {
            $query->whereIn('sp_state', [
                Torrent::PROMOTION_FREE,
                Torrent::PROMOTION_FREE_TWO_TIMES_UP,
            ]);
        }

        foreach (self::FACETS as $property => [$modelClass, $column]) {
            unset($modelClass);
            $selected = array_values(array_filter(array_map('intval', $this->{$property} ?? [])));
            if (! empty($selected)) {
                $query->whereIn($column, $selected);
            }
        }

        return $query;
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
}
