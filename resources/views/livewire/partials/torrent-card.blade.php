@php
    /** @var \App\Models\Torrent $torrent */
    /** @var string $cover */
    $spState = (int) ($torrent->getRawOriginal('sp_state') ?? \App\Models\Torrent::PROMOTION_NORMAL);

    $promotionBadge = match ($spState) {
        \App\Models\Torrent::PROMOTION_FREE => ['Free', 'bg-success-100 text-success-800 dark:bg-success-900/40 dark:text-success-200'],
        \App\Models\Torrent::PROMOTION_TWO_TIMES_UP => ['2× Up', 'bg-primary-100 text-primary-800 dark:bg-primary-900/40 dark:text-primary-200'],
        \App\Models\Torrent::PROMOTION_FREE_TWO_TIMES_UP => ['Free / 2× Up', 'bg-success-100 text-success-800 dark:bg-success-900/40 dark:text-success-200'],
        \App\Models\Torrent::PROMOTION_HALF_DOWN => ['50%', 'bg-warning-100 text-warning-800 dark:bg-warning-900/40 dark:text-warning-200'],
        \App\Models\Torrent::PROMOTION_HALF_DOWN_TWO_TIMES_UP => ['50% / 2× Up', 'bg-warning-100 text-warning-800 dark:bg-warning-900/40 dark:text-warning-200'],
        \App\Models\Torrent::PROMOTION_ONE_THIRD_DOWN => ['30%', 'bg-warning-100 text-warning-800 dark:bg-warning-900/40 dark:text-warning-200'],
        default => null,
    };
    $cover = $cover ?? '';
@endphp

<a
    href="/details.php?id={{ $torrent->id }}"
    wire:key="torrent-{{ $torrent->id }}"
    class="group flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-primary-700"
>
    <div
        class="relative aspect-[2/3] w-full overflow-hidden bg-gradient-to-br from-zinc-100 to-zinc-200 dark:from-zinc-800 dark:to-zinc-900"
        x-data="{ loaded: false, errored: false }"
    >
        @if ($cover !== '')
            <img
                src="{{ $cover }}"
                alt="{{ $torrent->name }}"
                loading="lazy"
                decoding="async"
                referrerpolicy="no-referrer"
                x-on:load="loaded = true"
                x-on:error="errored = true"
                x-bind:class="loaded ? 'opacity-100' : 'opacity-0'"
                class="absolute inset-0 h-full w-full object-cover transition-opacity duration-300 group-hover:scale-[1.02]"
            >
        @endif
        <div
            @if ($cover !== '')
                x-show="!loaded || errored"
                x-cloak
            @endif
            class="absolute inset-0 flex items-center justify-center p-4"
        >
            <div class="text-center">
                <svg class="mx-auto h-10 w-10 text-zinc-400 dark:text-zinc-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6.5v11A1.5 1.5 0 005.5 19h13a1.5 1.5 0 001.5-1.5v-11A1.5 1.5 0 0018.5 5h-13A1.5 1.5 0 004 6.5z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4-4 3 3 4-5 5 6"/>
                    <circle cx="9" cy="9" r="1.5"/>
                </svg>
                <span class="mt-2 block truncate text-[10px] font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-500">
                    @if ($torrent->basic_category)
                        {{ $torrent->basic_category->name }}
                    @else
                        No cover
                    @endif
                </span>
            </div>
        </div>
        @if ($promotionBadge)
            <span class="absolute left-2 top-2 rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider shadow-sm {{ $promotionBadge[1] }}">
                {{ $promotionBadge[0] }}
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-3 p-4">
        <div>
            <h3 class="line-clamp-2 text-sm font-semibold leading-snug text-zinc-900 group-hover:text-primary-600 dark:text-zinc-50 dark:group-hover:text-primary-400">
                {{ $torrent->name }}
            </h3>
            @if (! empty($torrent->small_descr))
                <p class="mt-1 line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $torrent->small_descr }}</p>
            @endif
        </div>

        <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
            @if ($torrent->basic_category)
                <span class="rounded-md bg-zinc-100 px-2 py-0.5 font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                    {{ $torrent->basic_category->name }}
                </span>
            @endif
            <span>{{ \App\Livewire\TorrentBrowse::formatBytes((int) $torrent->size) }}</span>
            <span>•</span>
            <time datetime="{{ optional($torrent->added)->toIso8601String() }}" title="{{ optional($torrent->added)->format('Y-m-d H:i') }}">
                {{ optional($torrent->added)->diffForHumans() }}
            </time>
        </div>

        <div class="mt-auto flex items-center gap-3 border-t border-zinc-100 pt-3 text-xs dark:border-zinc-800">
            <span class="inline-flex items-center gap-1 text-success-600 dark:text-success-400" title="Seeders">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                {{ number_format((int) $torrent->seeders) }}
            </span>
            <span class="inline-flex items-center gap-1 text-danger-600 dark:text-danger-400" title="Leechers">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                {{ number_format((int) $torrent->leechers) }}
            </span>
            <span class="inline-flex items-center gap-1 text-zinc-500 dark:text-zinc-400" title="Snatched">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                {{ number_format((int) $torrent->times_completed) }}
            </span>
        </div>
    </div>
</a>
