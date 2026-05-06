<div class="space-y-6" x-data="{ filtersOpen: false }">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">Browse torrents</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Modern Livewire-powered browse. Filters update instantly; URL is shareable.
            </p>
        </div>
        <a
            href="/torrents.php"
            class="inline-flex items-center gap-1 self-start rounded-md border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
            title="Open the legacy torrent listing (bookmarks, dead-only filter, advanced search)"
        >
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14L21 3m0 0v7m0-7h-7M5 5h6v6"/>
            </svg>
            Legacy listing
        </a>
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
            <div class="md:col-span-3">
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
            <div class="flex items-end md:col-span-1">
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <input
                        type="checkbox"
                        wire:model.live="onlyFree"
                        class="h-4 w-4 rounded border-zinc-300 text-primary-600 focus:ring-primary-500 dark:border-zinc-600 dark:bg-zinc-800"
                    >
                    Free
                </label>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
            <span class="text-zinc-500 dark:text-zinc-400">
                {{ number_format($total) }} {{ Str::plural('result', $total) }}
            </span>
            @if (! empty($this->facets))
                <button
                    type="button"
                    @click="filtersOpen = !filtersOpen"
                    class="inline-flex items-center gap-1 rounded-md border border-zinc-200 px-2 py-1 font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.59a1 1 0 01-.29.7l-6.42 6.42a1 1 0 00-.29.7V21l-4-2v-6.59a1 1 0 00-.29-.7L3.29 7.3A1 1 0 013 6.59V4z"/></svg>
                    <span x-text="filtersOpen ? 'Hide filters' : 'Show filters'"></span>
                </button>
            @endif
            @if ($search !== '' || $category !== '' || $sort !== 'newest' || $onlyFree
                || $this->hasActiveFacets())
                <button
                    type="button"
                    wire:click="clearFilters"
                    class="rounded-md border border-zinc-200 px-2 py-1 text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                >
                    Clear all
                </button>
            @endif
        </div>
    </div>

    <div class="flex flex-col gap-6 lg:flex-row">
        @if (! empty($this->facets))
            <aside
                x-show="filtersOpen || window.innerWidth >= 1024"
                x-cloak
                class="flex-shrink-0 space-y-4 lg:w-64"
            >
                @foreach ($this->facets as $facet)
                    <details
                        class="rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900"
                        @if (! empty($this->{$facet['property']})) open @endif
                    >
                        <summary class="flex cursor-pointer items-center justify-between text-xs font-medium uppercase tracking-wider text-zinc-500 select-none dark:text-zinc-400">
                            <span>{{ $facet['label'] }}</span>
                            @if (count($this->{$facet['property']}) > 0)
                                <span class="rounded-full bg-primary-100 px-2 py-0.5 text-[10px] font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">
                                    {{ count($this->{$facet['property']}) }}
                                </span>
                            @endif
                        </summary>
                        <div class="mt-2 space-y-1.5 max-h-64 overflow-y-auto pr-1">
                            @foreach ($facet['rows'] as $row)
                                <label class="flex cursor-pointer items-center gap-2 rounded-md px-1.5 py-1 text-sm text-zinc-700 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    <input
                                        type="checkbox"
                                        wire:model.live="{{ $facet['property'] }}"
                                        value="{{ $row->id }}"
                                        class="h-4 w-4 rounded border-zinc-300 text-primary-600 focus:ring-primary-500 dark:border-zinc-600 dark:bg-zinc-800"
                                    >
                                    <span>{{ $row->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </aside>
        @endif

        <div class="flex-1">
            @if ($torrents->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-base font-medium text-zinc-700 dark:text-zinc-300">No torrents match your filters.</p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Try clearing filters or broadening the search.</p>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($torrents as $torrent)
                        @include('livewire.partials.torrent-card', [
                            'torrent' => $torrent,
                            'cover' => $covers[$torrent->id] ?? '',
                        ])
                    @endforeach
                </div>

                @if ($hasMore)
                    <div
                        class="mt-6 flex justify-center"
                        x-data="{
                            init() {
                                const io = new IntersectionObserver((entries) => {
                                    if (entries[0].isIntersecting) {
                                        $wire.loadMore();
                                    }
                                }, { rootMargin: '400px 0px' });
                                io.observe(this.$el);
                            },
                        }"
                    >
                        <button
                            type="button"
                            wire:click="loadMore"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-md border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700 shadow-sm transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700"
                        >
                            <svg
                                class="h-4 w-4 animate-spin"
                                wire:loading
                                wire:target="loadMore"
                                fill="none"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                            </svg>
                            <span wire:loading.remove wire:target="loadMore">Load more</span>
                            <span wire:loading wire:target="loadMore">Loading…</span>
                        </button>
                    </div>
                @else
                    <p class="mt-6 text-center text-xs text-zinc-500 dark:text-zinc-400">
                        End of results — {{ number_format($total) }} {{ Str::plural('torrent', $total) }} shown.
                    </p>
                @endif
            @endif
        </div>
    </div>
</div>
