@php
    /** @var \App\Livewire\TorrentBrowse $this */
    $spStateOptions = \App\Livewire\TorrentBrowse::spStateOptions();
    $includeDeadOptions = \App\Livewire\TorrentBrowse::includeDeadOptions();
    $bookmarkOptions = \App\Livewire\TorrentBrowse::bookmarkOptions();
    $subcategoryOptions = $this->subcategoryOptions;
    $authenticated = auth('nexus-web')->check();

    $rangesDirty = $sizeMin !== null
        || $sizeMax !== null
        || $seedersMin !== null
        || $seedersMax !== null
        || $leechersMin !== null
        || $leechersMax !== null
        || $snatchesMin !== null
        || $snatchesMax !== null;

    $subcatsDirty = $source !== 0
        || $medium !== 0
        || $codec !== 0
        || $standard !== 0
        || $processing !== 0
        || $team !== 0
        || $audiocodec !== 0;

    $advancedOpen = $rangesDirty || $subcatsDirty;

    $filtersDirty = $search !== ''
        || $category !== ''
        || $sort !== 'newest'
        || $spState !== \App\Livewire\TorrentBrowse::SPSTATE_ALL
        || $includeDead !== \App\Livewire\TorrentBrowse::INCLUDE_DEAD_ACTIVE
        || $bookmarked !== \App\Livewire\TorrentBrowse::BOOKMARK_ALL
        || $tagId !== 0
        || $mode !== \App\Livewire\TorrentBrowse::MODE_TORRENTS
        || $rangesDirty
        || $subcatsDirty;
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-2">
        <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">Browse torrents</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Modern Livewire-powered browse. Filters update instantly; URL is shareable.
            <a href="/browse?legacy=1" class="ml-1 text-primary-600 hover:underline dark:text-primary-400">
                Switch to legacy /torrents.php
            </a>
        </p>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="grid gap-3 md:grid-cols-12">
            <div class="md:col-span-5">
                <label for="search" class="mb-1 block text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                    Search
                </label>
                <input
                    id="search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Title or description…"
                    class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                >
            </div>
            <div class="md:col-span-3">
                <label for="category" class="mb-1 block text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                    Category
                </label>
                <select
                    id="category"
                    wire:model.live="category"
                    class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                >
                    <option value="">All categories</option>
                    @foreach ($this->categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-4">
                <label for="sort" class="mb-1 block text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                    Sort by
                </label>
                <select
                    id="sort"
                    wire:model.live="sort"
                    class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                >
                    @foreach ($this->sortOptions as $option)
                        <option value="{{ $option['key'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-3">
                <label for="spstate" class="mb-1 block text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                    Promotion
                </label>
                <select
                    id="spstate"
                    wire:model.live="spState"
                    class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                >
                    @foreach ($spStateOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-3">
                <label for="incldead" class="mb-1 block text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                    Visibility
                </label>
                <select
                    id="incldead"
                    wire:model.live="includeDead"
                    class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                >
                    @foreach ($includeDeadOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if ($authenticated)
                <div class="md:col-span-3">
                    <label for="inclbookmarked" class="mb-1 block text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        Bookmarks
                    </label>
                    <select
                        id="inclbookmarked"
                        wire:model.live="bookmarked"
                        class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                    >
                        @foreach ($bookmarkOptions as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="md:col-span-3">
                <label for="tag_id" class="mb-1 block text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                    Tag
                </label>
                <select
                    id="tag_id"
                    wire:model.live="tagId"
                    class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                >
                    <option value="0">All tags</option>
                    @foreach ($this->tags as $tag)
                        <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                    @endforeach
                </select>
            </div>

        </div>

        <details class="mt-4 group" @if ($advancedOpen) open @endif data-testid="advanced-filters">
            <summary class="cursor-pointer select-none text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                <span class="inline-flex items-center gap-1">
                    <span class="transition-transform group-open:rotate-90">▸</span>
                    Advanced filters
                    @if ($advancedOpen)
                        <span class="rounded-full bg-primary-100 px-2 py-0.5 text-xs font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-200">active</span>
                    @endif
                </span>
            </summary>

            <div class="mt-3 space-y-4">
                <div>
                    <div class="mb-2 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Ranges</div>
                    <div class="grid gap-3 md:grid-cols-12">
                        <div class="md:col-span-3">
                            <label for="size_begin" class="mb-1 block text-xs text-zinc-500 dark:text-zinc-400">Size ≥ (GB)</label>
                            <input
                                id="size_begin"
                                type="number"
                                min="0"
                                inputmode="numeric"
                                wire:model.live.debounce.400ms="sizeMin"
                                class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                            >
                        </div>
                        <div class="md:col-span-3">
                            <label for="size_end" class="mb-1 block text-xs text-zinc-500 dark:text-zinc-400">Size ≤ (GB)</label>
                            <input
                                id="size_end"
                                type="number"
                                min="0"
                                inputmode="numeric"
                                wire:model.live.debounce.400ms="sizeMax"
                                class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                            >
                        </div>
                        <div class="md:col-span-3">
                            <label for="seeders_begin" class="mb-1 block text-xs text-zinc-500 dark:text-zinc-400">Seeders ≥</label>
                            <input
                                id="seeders_begin"
                                type="number"
                                min="0"
                                inputmode="numeric"
                                wire:model.live.debounce.400ms="seedersMin"
                                class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                            >
                        </div>
                        <div class="md:col-span-3">
                            <label for="seeders_end" class="mb-1 block text-xs text-zinc-500 dark:text-zinc-400">Seeders ≤</label>
                            <input
                                id="seeders_end"
                                type="number"
                                min="0"
                                inputmode="numeric"
                                wire:model.live.debounce.400ms="seedersMax"
                                class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                            >
                        </div>
                        <div class="md:col-span-3">
                            <label for="leechers_begin" class="mb-1 block text-xs text-zinc-500 dark:text-zinc-400">Leechers ≥</label>
                            <input
                                id="leechers_begin"
                                type="number"
                                min="0"
                                inputmode="numeric"
                                wire:model.live.debounce.400ms="leechersMin"
                                class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                            >
                        </div>
                        <div class="md:col-span-3">
                            <label for="leechers_end" class="mb-1 block text-xs text-zinc-500 dark:text-zinc-400">Leechers ≤</label>
                            <input
                                id="leechers_end"
                                type="number"
                                min="0"
                                inputmode="numeric"
                                wire:model.live.debounce.400ms="leechersMax"
                                class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                            >
                        </div>
                        <div class="md:col-span-3">
                            <label for="times_completed_begin" class="mb-1 block text-xs text-zinc-500 dark:text-zinc-400">Snatches ≥</label>
                            <input
                                id="times_completed_begin"
                                type="number"
                                min="0"
                                inputmode="numeric"
                                wire:model.live.debounce.400ms="snatchesMin"
                                class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                            >
                        </div>
                        <div class="md:col-span-3">
                            <label for="times_completed_end" class="mb-1 block text-xs text-zinc-500 dark:text-zinc-400">Snatches ≤</label>
                            <input
                                id="times_completed_end"
                                type="number"
                                min="0"
                                inputmode="numeric"
                                wire:model.live.debounce.400ms="snatchesMax"
                                class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                            >
                        </div>
                    </div>
                </div>

                <div>
                    <div class="mb-2 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Sub-categories</div>
                    <div class="grid gap-3 md:grid-cols-12">
                        @foreach ($subcategoryOptions as $property => $entry)
                            <div class="md:col-span-3" data-testid="subcat-{{ $property }}">
                                <label for="subcat_{{ $property }}" class="mb-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ $entry['label'] }}</label>
                                <select
                                    id="subcat_{{ $property }}"
                                    wire:model.live="{{ $property }}"
                                    class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                >
                                    <option value="0">Any</option>
                                    @foreach ($entry['options'] as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </details>

        @if ($filtersDirty)
            <div class="mt-3 flex items-center gap-2 text-xs">
                <span class="text-zinc-500 dark:text-zinc-400">
                    {{ $torrents->total() }} {{ Str::plural('result', $torrents->total()) }}
                </span>
                <button
                    type="button"
                    wire:click="clearFilters"
                    class="rounded-md border border-zinc-200 px-2 py-1 text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                >
                    Clear filters
                </button>
            </div>
        @endif
    </div>

    @if ($torrents->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-base font-medium text-zinc-700 dark:text-zinc-300">No torrents match your filters.</p>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Try clearing filters or broadening the search.</p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($torrents as $torrent)
                @include('livewire.partials.torrent-card', ['torrent' => $torrent])
            @endforeach
        </div>

        <div class="pt-2">
            {{ $torrents->onEachSide(1)->links() }}
        </div>
    @endif
</div>
