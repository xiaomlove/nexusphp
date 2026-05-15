@php
    /** @var \App\Models\Torrent $torrent */
    /** @var \App\Models\User|null $owner */
    /** @var \App\Models\TorrentOperationLog|null $banReason */
    /** @var array{0:string,1:string}|null $promotionBadge */
    /** @var array<string,string> $taxonomy */
    /** @var \Illuminate\Support\Collection<int,\App\Models\File> $files */
    /** @var array{seeders:\Illuminate\Support\Collection<int,\App\Models\Peer>,leechers:\Illuminate\Support\Collection<int,\App\Models\Peer>} $peerGroups */
    /** @var array<string,string> $hotMeter */
    /** @var string $descriptionHtml */
    /** @var int $viewerId */
    $sizeBytes = (int) $torrent->size;
    $formattedSize = \App\Livewire\TorrentBrowse::formatBytes($sizeBytes);
    $now = time();
    $formatPeerSize = static fn (int $bytes) => \App\Livewire\TorrentBrowse::formatBytes(max(0, $bytes));
    $formatDuration = static function (int $seconds): string {
        $seconds = max(0, $seconds);
        if ($seconds < 60) {
            return $seconds.'s';
        }
        if ($seconds < 3600) {
            return floor($seconds / 60).'m';
        }
        if ($seconds < 86400) {
            return floor($seconds / 3600).'h '.floor(($seconds % 3600) / 60).'m';
        }

        return floor($seconds / 86400).'d '.floor(($seconds % 86400) / 3600).'h';
    };
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

    @if (! empty($hotMeter))
        <x-ui.card>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                Hot meter
            </h2>
            <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4" data-test-id="hot-meter">
                @foreach ($hotMeter as $label => $value)
                    @php
                        $slug = \Illuminate\Support\Str::slug($label);
                    @endphp
                    <div data-test-id="hot-meter-{{ $slug }}">
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            {{ $label }}
                        </dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
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
                Small description
            </h2>
            <p class="text-sm text-zinc-700 dark:text-zinc-200">{{ $torrent->small_descr }}</p>
        </x-ui.card>
    @endif

    @if ($descriptionHtml !== '')
        <x-ui.card>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                Description
            </h2>
            {{-- $descriptionHtml is already HTML-escaped by BbcodeRenderer::toHtml(); the
                 only HTML tags it emits are the safe subset documented on the renderer. --}}
            <div class="prose prose-sm max-w-none text-zinc-700 dark:prose-invert dark:text-zinc-200"
                 data-test-id="torrent-description">
                {!! $descriptionHtml !!}
            </div>
        </x-ui.card>
    @endif

    <x-ui.card>
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
            Files ({{ number_format($files->count()) }})
        </h2>
        @if ($files->isEmpty())
            <p class="text-sm italic text-zinc-500 dark:text-zinc-400" data-test-id="files-empty">
                No file list available.
            </p>
        @else
            <div class="overflow-x-auto" data-test-id="files-table">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="py-2 pr-4 font-medium">#</th>
                            <th class="py-2 pr-4 font-medium">File name</th>
                            <th class="py-2 pr-4 font-medium text-right">Size</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach ($files as $i => $file)
                            <tr data-test-id="file-row" data-file-id="{{ $file->id }}">
                                <td class="py-2 pr-4 text-zinc-500 dark:text-zinc-400">{{ $i + 1 }}</td>
                                <td class="py-2 pr-4 break-all text-zinc-900 dark:text-zinc-100">{{ $file->filename }}</td>
                                <td class="py-2 pr-4 text-right font-mono text-zinc-900 dark:text-zinc-100">
                                    {{ \App\Livewire\TorrentBrowse::formatBytes((int) $file->size) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card>
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
            Peers
        </h2>
        @foreach ([
            'seeders' => ['label' => 'Seeders', 'rows' => $peerGroups['seeders'], 'empty' => 'No seeders.'],
            'leechers' => ['label' => 'Leechers', 'rows' => $peerGroups['leechers'], 'empty' => 'No leechers.'],
        ] as $key => $section)
            <section class="mt-4 first:mt-0" data-test-id="peers-{{ $key }}">
                <h3 class="mb-2 text-sm font-semibold text-zinc-700 dark:text-zinc-200">
                    {{ $section['label'] }} ({{ number_format($section['rows']->count()) }})
                </h3>
                @if ($section['rows']->isEmpty())
                    <p class="text-sm italic text-zinc-500 dark:text-zinc-400" data-test-id="peers-{{ $key }}-empty">
                        {{ $section['empty'] }}
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                <tr>
                                    <th class="py-2 pr-4 font-medium">User</th>
                                    <th class="py-2 pr-4 font-medium">Connectable</th>
                                    <th class="py-2 pr-4 font-medium text-right">Uploaded</th>
                                    <th class="py-2 pr-4 font-medium text-right">Downloaded</th>
                                    <th class="py-2 pr-4 font-medium text-right">Ratio</th>
                                    <th class="py-2 pr-4 font-medium text-right">Complete</th>
                                    <th class="py-2 pr-4 font-medium text-right">Connected</th>
                                    <th class="py-2 pr-4 font-medium text-right">Idle</th>
                                    <th class="py-2 pr-4 font-medium">Client</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @foreach ($section['rows'] as $peer)
                                    @php
                                        $displayUsername = $peer->getAttribute('display_username');
                                        $isOwnRow = $viewerId !== 0 && (int) $peer->userid === $viewerId;
                                        $startedTs = $peer->started ? $peer->started->timestamp : $now;
                                        $lastActionTs = $peer->last_action ? $peer->last_action->timestamp : $now;
                                        $uploaded = (int) $peer->uploaded;
                                        $downloaded = (int) $peer->downloaded;
                                        if ($downloaded > 0) {
                                            $ratioText = number_format($uploaded / $downloaded, 3);
                                        } elseif ($uploaded > 0) {
                                            $ratioText = '∞';
                                        } else {
                                            $ratioText = '—';
                                        }
                                        $completePct = $sizeBytes > 0
                                            ? max(0.0, min(100.0, 100 * (1 - ((int) $peer->to_go / $sizeBytes))))
                                            : 0.0;
                                    @endphp
                                    <tr data-test-id="peer-row"
                                        data-peer-id="{{ $peer->id }}"
                                        data-user-id="{{ (int) $peer->userid }}"
                                        @class(['bg-amber-50 dark:bg-amber-900/20' => $isOwnRow])>
                                        <td class="py-2 pr-4 break-all text-zinc-900 dark:text-zinc-100" data-test-id="peer-user">
                                            @if ($displayUsername === null)
                                                <span class="italic text-zinc-500 dark:text-zinc-400">Anonymous</span>
                                            @else
                                                {{ $displayUsername }}
                                            @endif
                                        </td>
                                        <td class="py-2 pr-4 text-zinc-900 dark:text-zinc-100">
                                            {{ $peer->connectable === \App\Models\Peer::CONNECTABLE_YES ? 'Yes' : 'No' }}
                                        </td>
                                        <td class="py-2 pr-4 text-right font-mono text-zinc-900 dark:text-zinc-100">
                                            {{ $formatPeerSize($uploaded) }}
                                        </td>
                                        <td class="py-2 pr-4 text-right font-mono text-zinc-900 dark:text-zinc-100">
                                            {{ $formatPeerSize($downloaded) }}
                                        </td>
                                        <td class="py-2 pr-4 text-right font-mono text-zinc-900 dark:text-zinc-100" data-test-id="peer-ratio">
                                            {{ $ratioText }}
                                        </td>
                                        <td class="py-2 pr-4 text-right font-mono text-zinc-900 dark:text-zinc-100">
                                            {{ number_format($completePct, 2) }}%
                                        </td>
                                        <td class="py-2 pr-4 text-right font-mono text-zinc-900 dark:text-zinc-100">
                                            {{ $formatDuration($now - $startedTs) }}
                                        </td>
                                        <td class="py-2 pr-4 text-right font-mono text-zinc-900 dark:text-zinc-100">
                                            {{ $formatDuration($now - $lastActionTs) }}
                                        </td>
                                        <td class="py-2 pr-4 text-zinc-900 dark:text-zinc-100">{{ $peer->agent ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endforeach
    </x-ui.card>

    <div class="flex flex-wrap items-center gap-2">
        <x-ui.button href="/download.php?id={{ $torrent->id }}" variant="primary" data-test-id="download-btn">
            Download .torrent
        </x-ui.button>
        <x-ui.button href="/browse" variant="secondary">
            ← Back to browse
        </x-ui.button>
    </div>
</div>
