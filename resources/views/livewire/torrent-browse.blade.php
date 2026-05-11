@php
    /** @var \App\Livewire\TorrentBrowse $this */
    $spStateOptions = \App\Livewire\TorrentBrowse::spStateOptions();
    $includeDeadOptions = \App\Livewire\TorrentBrowse::includeDeadOptions();
    $bookmarkOptions = \App\Livewire\TorrentBrowse::bookmarkOptions();
    $authenticated = auth('nexus-web')->check();

    $filtersDirty = $search !== ''
        || $category !== ''
        || $sort !== 'newest'
        || $spState !== \App\Livewire\TorrentBrowse::SPSTATE_ALL
        || $includeDead !== \App\Livewire\TorrentBrowse::INCLUDE_DEAD_ACTIVE
        || $bookmarked !== \App\Livewire\TorrentBrowse::BOOKMARK_ALL
        || $tagId !== 0
        || $mode !== \App\Livewire\TorrentBrowse::MODE_TORRENTS;
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
