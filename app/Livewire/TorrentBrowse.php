<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Torrent;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TorrentBrowse extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'cat', except: '')]
    public string $category = '';

    #[Url(as: 'sort', except: 'newest')]
    public string $sort = 'newest';

    #[Url(as: 'free', except: false)]
    public bool $onlyFree = false;

    private const SORTS = [
        'newest' => ['column' => 'added', 'direction' => 'desc', 'label' => 'Newest'],
        'oldest' => ['column' => 'added', 'direction' => 'asc', 'label' => 'Oldest'],
        'seeders' => ['column' => 'seeders', 'direction' => 'desc', 'label' => 'Most seeded'],
        'leechers' => ['column' => 'leechers', 'direction' => 'desc', 'label' => 'Most leeched'],
        'snatches' => ['column' => 'times_completed', 'direction' => 'desc', 'label' => 'Most snatched'],
        'largest' => ['column' => 'size', 'direction' => 'desc', 'label' => 'Largest'],
        'smallest' => ['column' => 'size', 'direction' => 'asc', 'label' => 'Smallest'],
    ];

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

    public function updatingOnlyFree(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'category', 'sort', 'onlyFree']);
        $this->sort = 'newest';
        $this->resetPage();
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

    public function render(): View
    {
        $sortConfig = self::SORTS[$this->sort] ?? self::SORTS['newest'];

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
}
