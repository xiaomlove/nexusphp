@php
    /** @var \App\Models\Torrent $torrent */
    /** @var \App\Models\User|null $owner */
    /** @var \App\Models\TorrentOperationLog|null $banReason */
    /** @var array{0:string,1:string}|null $promotionBadge */
    /** @var array<string,string> $taxonomy */
    $sizeBytes = (int) $torrent->size;
    $formattedSize = \App\Livewire\TorrentBrowse::formatBytes($sizeBytes);
    $isBanned = $torrent->banned === \App\Models\Torrent::BANNED_YES;
    $isInvisible = $torrent->visible === \App\Models\Torrent::VISIBLE_NO;
    $isAnonymous = $torrent->anonymous === 'yes';
    $hasHr = (int) $torrent->hr === \App\Models\Torrent::HR_YES;
    $category = $torrent->basic_category;
    $rawInfoHash = (string) ($torrent->getRawOriginal('info_hash') ?? '');
    $infoHashHex = $rawInfoHash !== '' ? bin2hex($rawInfoHash) : '';
    $price = (int) ($torrent->price ?? 0);
    $numFiles = (int) ($torrent->numfiles ?? 0);
@endphp

<div class="space-y-6" data-test-id="torrent-detail" data-torrent-id="{{ $torrent->id }}">
    @if ($banReason)
        <div data-test-id="ban-reason"
             class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-100">
            <p class="font-semibold">
                {{ nexus_trans('torrent.approval.deny_comment_show', ['reason' => (string) ($banReason->comment ?? '')]) }}
            </p>
        </div>
    @endif

    <x-ui.page-header :title="$torrent->name">
        <x-slot:description>
            @if (! empty($torrent->small_descr))
                {{ $torrent->small_descr }}
            @endif
            <a href="/details.php?id={{ $torrent->id }}&legacy=1"
               class="ml-1 text-primary-600 hover:underline dark:text-primary-400"
               data-test-id="legacy-detail-link">
                Switch to legacy /details.php
            </a>
        </x-slot:description>
    </x-ui.page-header>

    <div class="flex flex-wrap items-center gap-2">
        @if ($category)
            <x-ui.badge variant="neutral" size="sm">{{ $category->name }}</x-ui.badge>
        @endif
        @if ($promotionBadge)
            <x-ui.badge :variant="$promotionBadge[1]" size="sm">{{ $promotionBadge[0] }}</x-ui.badge>
        @endif
        @if ($hasHr)
            <x-ui.badge variant="warning" size="sm">H&amp;R</x-ui.badge>
        @endif
        @if ($isBanned)
            <x-ui.badge variant="danger" size="sm">Banned</x-ui.badge>
        @endif
        @if ($isInvisible)
            <x-ui.badge variant="neutral" size="sm">Invisible</x-ui.badge>
        @endif
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat label="Size" :value="$formattedSize" />
        <x-ui.stat label="Seeders" :value="number_format((int) $torrent->seeders)" variant="success" />
        <x-ui.stat label="Leechers" :value="number_format((int) $torrent->leechers)" variant="danger" />
        <x-ui.stat label="Snatched" :value="number_format((int) $torrent->times_completed)" />
    </div>

    <x-ui.card>
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Uploader</dt>
                <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100" data-test-id="uploader">
                    @if ($isAnonymous)
                        <span class="italic text-zinc-500 dark:text-zinc-400">Anonymous</span>
                    @elseif ($owner)
                        {{ $owner->username }}
                    @else
                        <span class="italic text-zinc-500 dark:text-zinc-400">Unknown</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Added</dt>
                <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100" data-test-id="added">
                    @if ($torrent->added)
                        <time datetime="{{ $torrent->added->toIso8601String() }}" title="{{ $torrent->added->format('Y-m-d H:i:s') }}">
                            {{ $torrent->added->diffForHumans() }}
                        </time>
                    @else
                        &mdash;
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">File name</dt>
                <dd class="mt-1 break-all text-sm text-zinc-900 dark:text-zinc-100">
                    {{ $torrent->filename ?: '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Visibility</dt>
                <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                    {{ $isInvisible ? 'Not visible' : 'Public' }}
                </dd>
            </div>
            @if ($numFiles > 0)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Files</dt>
                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100" data-test-id="numfiles">
                        {{ number_format($numFiles) }}
                    </dd>
                </div>
            @endif
            @if (! empty($torrent->save_as))
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Save as</dt>
                    <dd class="mt-1 break-all text-sm text-zinc-900 dark:text-zinc-100" data-test-id="save-as">
                        {{ $torrent->save_as }}
                    </dd>
                </div>
            @endif
            @if ($price > 0)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Price</dt>
                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100" data-test-id="price">
                        {{ number_format($price) }}
                    </dd>
                </div>
            @endif
            @if ($infoHashHex !== '')
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Info hash</dt>
                    <dd class="mt-1 break-all font-mono text-xs text-zinc-900 dark:text-zinc-100" data-test-id="info-hash">
                        {{ $infoHashHex }}
                    </dd>
                </div>
            @endif
        </dl>
    </x-ui.card>

    @if (! empty($taxonomy))
        <x-ui.card>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                Technical metadata
            </h2>
            <dl class="grid gap-4 sm:grid-cols-2" data-test-id="taxonomy">
                @foreach ($taxonomy as $label => $value)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100" data-test-id="tax-{{ \Illuminate\Support\Str::slug($label) }}">
                            {{ $value }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>
    @endif

    @if (! empty($torrent->small_descr))
        <x-ui.card>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                Description
            </h2>
            <p class="text-sm text-zinc-700 dark:text-zinc-200">{{ $torrent->small_descr }}</p>
        </x-ui.card>
    @endif

    <div class="flex flex-wrap items-center gap-2">
        <x-ui.button href="/download.php?id={{ $torrent->id }}" variant="primary" data-test-id="download-btn">
            Download .torrent
        </x-ui.button>
        <x-ui.button href="/browse" variant="secondary">
            ← Back to browse
        </x-ui.button>
    </div>
</div>
