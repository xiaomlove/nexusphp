<div class="space-y-6">
    <div class="flex flex-col gap-2">
        <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">Browse torrents</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Modern Livewire-powered browse. Filters update instantly; URL is shareable.
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

        @if ($search !== '' || $category !== '' || $sort !== 'newest' || $onlyFree)
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
