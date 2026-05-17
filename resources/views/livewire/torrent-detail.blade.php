@php
    /** @var \App\Models\Torrent $torrent */
    /** @var \App\Models\User|null $owner */
    /** @var \App\Models\TorrentOperationLog|null $banReason */
    /** @var array{0:string,1:string}|null $promotionBadge */
    /** @var array<string,string> $taxonomy */
    /** @var \Illuminate\Support\Collection<int,\App\Models\File> $files */
    /** @var array{seeders:\Illuminate\Support\Collection<int,\App\Models\Peer>,leechers:\Illuminate\Support\Collection<int,\App\Models\Peer>} $peerGroups */
    /** @var \Illuminate\Support\Collection<int,\App\Models\Snatch> $snatches */
    /** @var \Illuminate\Support\Collection<int,\App\Models\Comment> $comments */
    /** @var array<string,string> $hotMeter */
    /** @var string $descriptionHtml */
    /** @var string $technicalInfoHtml */
    /** @var array{html:string,view:string}|null $nfoBlock */
    /** @var int $viewerId */
    /** @var bool $isAuthed */
    /** @var bool $isBookmarked */
    /** @var bool $hasThanked */
    /** @var \Illuminate\Support\Collection<int,string> $thanksRecent */
    /** @var int $thanksTotal */
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

    @if ($isAuthed)
        <x-ui.card>
            <div class="flex flex-wrap items-center gap-3"
                 data-test-id="torrent-actions">
                <button type="button"
                        wire:click="toggleBookmark"
                        wire:loading.attr="disabled"
                        data-test-id="bookmark-toggle"
                        data-bookmarked="{{ $isBookmarked ? 'yes' : 'no' }}"
                        class="inline-flex items-center rounded border border-primary-500 bg-primary-50 px-3 py-1 text-sm font-medium text-primary-700 hover:bg-primary-100 disabled:opacity-50 dark:border-primary-400 dark:bg-primary-950/40 dark:text-primary-200 dark:hover:bg-primary-950/70">
                    {{ $isBookmarked ? 'Bookmarked — click to remove' : 'Bookmark this torrent' }}
                </button>

                <button type="button"
                        wire:click="sayThanks"
                        wire:loading.attr="disabled"
                        @disabled($hasThanked)
                        data-test-id="thanks-button"
                        data-thanked="{{ $hasThanked ? 'yes' : 'no' }}"
                        class="inline-flex items-center rounded border border-zinc-300 bg-white px-3 py-1 text-sm font-medium text-zinc-800 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800">
                    {{ $hasThanked ? 'Thanks said' : 'Say thanks' }}
                </button>

                <div class="text-sm text-zinc-600 dark:text-zinc-300"
                     data-test-id="thanks-by"
                     data-thanks-total="{{ $thanksTotal }}">
                    @if ($thanksTotal === 0)
                        <span class="italic text-zinc-500 dark:text-zinc-400">No thanks yet — be the first.</span>
                    @else
                        <span>Thanks by:</span>
                        <span class="ml-1 break-all" data-test-id="thanks-recent">
                            {{ $thanksRecent->implode(', ') }}
                        </span>
                        @if ($thanksTotal > $thanksRecent->count())
                            <span class="ml-1 text-zinc-500 dark:text-zinc-400"
                                  data-test-id="thanks-more">
                                and {{ number_format($thanksTotal) }} users in total
                            </span>
                        @endif
                    @endif
                </div>
            </div>
        </x-ui.card>
    @endif

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

    @if ($technicalInfoHtml !== '')
        <x-ui.card>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                Technical info
            </h2>
            {{-- $technicalInfoHtml is produced by the same legacy renderers the
                 legacy details.php page calls (TechnicalInformation /
                 BdInfoExtra::renderOnDetailsPage). Self-contained styling
                 inside its own .nti-* / .bdinfo-* class namespace. --}}
            <div class="overflow-x-auto text-sm text-zinc-700 dark:text-zinc-200"
                 data-test-id="torrent-technical-info">
                {!! $technicalInfoHtml !!}
            </div>
        </x-ui.card>
    @endif

    @if ($nfoBlock !== null)
        <x-ui.card>
            <div class="mb-2 flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    NFO
                </h2>
                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ $nfoBlock['view'] }}-vy
                </span>
            </div>
            {{-- $nfoBlock['html'] is the IBM-437 → numeric-entity decoded blob
                 produced by App\Support\Codec::ibm437ToEntities. The legacy
                 details page wraps the same output in <pre>. --}}
            <pre data-test-id="torrent-nfo"
                 class="overflow-x-auto whitespace-pre rounded bg-zinc-100 p-3 text-xs leading-snug text-zinc-800 dark:bg-zinc-950 dark:text-zinc-200"
                 style="font-family: 'Courier New', monospace;">{!! $nfoBlock['html'] !!}</pre>
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

    <x-ui.card>
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
            Snatched ({{ number_format($snatches->count()) }})
        </h2>
        @if ($snatches->isEmpty())
            <p class="text-sm italic text-zinc-500 dark:text-zinc-400" data-test-id="snatches-empty">
                Nobody has finished this torrent yet.
            </p>
        @else
            <div class="overflow-x-auto" data-test-id="snatches-table">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="py-2 pr-4 font-medium">User</th>
                            <th class="py-2 pr-4 font-medium text-right">Uploaded</th>
                            <th class="py-2 pr-4 font-medium text-right">Downloaded</th>
                            <th class="py-2 pr-4 font-medium text-right">Ratio</th>
                            <th class="py-2 pr-4 font-medium text-right">Seed time</th>
                            <th class="py-2 pr-4 font-medium text-right">Leech time</th>
                            <th class="py-2 pr-4 font-medium text-right">Completed</th>
                            <th class="py-2 pr-4 font-medium text-right">Last action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach ($snatches as $snatch)
                            @php
                                $snatchUsername = $snatch->getAttribute('display_username');
                                $isOwnSnatchRow = $viewerId !== 0 && (int) $snatch->userid === $viewerId;
                                $snatchUploaded = (int) $snatch->uploaded;
                                $snatchDownloaded = (int) $snatch->downloaded;
                                if ($snatchDownloaded > 0) {
                                    $snatchRatio = number_format($snatchUploaded / $snatchDownloaded, 3);
                                } elseif ($snatchUploaded > 0) {
                                    $snatchRatio = '∞';
                                } else {
                                    $snatchRatio = '—';
                                }
                                $completedTs = $snatch->completedat ? $snatch->completedat->timestamp : null;
                                $lastActionTs = $snatch->last_action ? $snatch->last_action->timestamp : null;
                            @endphp
                            <tr data-test-id="snatch-row"
                                data-snatch-id="{{ $snatch->id }}"
                                data-user-id="{{ (int) $snatch->userid }}"
                                @class(['bg-amber-50 dark:bg-amber-900/20' => $isOwnSnatchRow])>
                                <td class="py-2 pr-4 break-all text-zinc-900 dark:text-zinc-100" data-test-id="snatch-user">
                                    @if ($snatchUsername === null)
                                        <span class="italic text-zinc-500 dark:text-zinc-400">Anonymous</span>
                                    @else
                                        {{ $snatchUsername }}
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-right font-mono text-zinc-900 dark:text-zinc-100">
                                    {{ $formatPeerSize($snatchUploaded) }}
                                </td>
                                <td class="py-2 pr-4 text-right font-mono text-zinc-900 dark:text-zinc-100">
                                    {{ $formatPeerSize($snatchDownloaded) }}
                                </td>
                                <td class="py-2 pr-4 text-right font-mono text-zinc-900 dark:text-zinc-100" data-test-id="snatch-ratio">
                                    {{ $snatchRatio }}
                                </td>
                                <td class="py-2 pr-4 text-right font-mono text-zinc-900 dark:text-zinc-100">
                                    {{ $formatDuration((int) $snatch->seedtime) }}
                                </td>
                                <td class="py-2 pr-4 text-right font-mono text-zinc-900 dark:text-zinc-100">
                                    {{ $formatDuration((int) $snatch->leechtime) }}
                                </td>
                                <td class="py-2 pr-4 text-right text-zinc-900 dark:text-zinc-100">
                                    {{ $completedTs !== null ? date('Y-m-d H:i', $completedTs) : '—' }}
                                </td>
                                <td class="py-2 pr-4 text-right text-zinc-900 dark:text-zinc-100">
                                    {{ $lastActionTs !== null ? date('Y-m-d H:i', $lastActionTs) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card>
        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400" data-test-id="comments-heading">
            Comments ({{ number_format($comments->count()) }})
        </h2>
        @if ($comments->isEmpty())
            <p class="text-sm italic text-zinc-500 dark:text-zinc-400" data-test-id="comments-empty">
                No comments yet.
            </p>
        @else
            <ul class="space-y-4" data-test-id="comments-list">
                @foreach ($comments as $comment)
                    @php
                        $commentUsername = $comment->getAttribute('display_username');
                        $isOwnComment = $viewerId !== 0 && (int) $comment->user === $viewerId;
                        $addedTs = $comment->added ? $comment->added->timestamp : null;
                        $editedTs = $comment->editdate ? $comment->editdate->timestamp : null;
                    @endphp
                    <li data-test-id="comment-row"
                        data-comment-id="{{ $comment->id }}"
                        data-user-id="{{ (int) $comment->user }}"
                        @class([
                            'rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900',
                            'ring-1 ring-amber-400/40' => $isOwnComment,
                        ])>
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100" data-test-id="comment-author">
                                @if ($commentUsername === null)
                                    <span class="italic text-zinc-500 dark:text-zinc-400">Anonymous</span>
                                @else
                                    {{ $commentUsername }}
                                @endif
                            </p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400" data-test-id="comment-added">
                                {{ $addedTs !== null ? date('Y-m-d H:i', $addedTs) : '—' }}
                            </p>
                        </div>
                        <div class="mt-2 break-words text-sm text-zinc-800 dark:text-zinc-100" data-test-id="comment-body">
                            {!! \App\Support\BbcodeRenderer::toHtml((string) $comment->text) !!}
                        </div>
                        @if ($editedTs !== null)
                            <p class="mt-2 text-xs italic text-zinc-500 dark:text-zinc-400" data-test-id="comment-edited">
                                Edited {{ date('Y-m-d H:i', $editedTs) }}
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
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
