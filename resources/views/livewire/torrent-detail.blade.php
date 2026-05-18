@php
    /** @var \App\Models\Torrent $torrent */
    /** @var \App\Models\User|null $owner */
    /** @var \App\Models\TorrentOperationLog|null $banReason */
    /** @var string|null $postWriteBanner */
    /** @var string|null $returnto */
    /** @var array{0:string,1:string}|null $promotionBadge */
    /** @var string|null $promotionSubtext */
    /** @var string $tagsHtml */
    /** @var array{isp:?string,up:?string,down:?string}|null $uploaderBandwidth */
    /** @var array<string,string> $taxonomy */
    /** @var \Illuminate\Support\Collection<int,\App\Models\File> $files */
    /** @var array{seeders:\Illuminate\Support\Collection<int,\App\Models\Peer>,leechers:\Illuminate\Support\Collection<int,\App\Models\Peer>} $peerGroups */
    /** @var \Illuminate\Support\Collection<int,\App\Models\Snatch> $snatches */
    /** @var \Illuminate\Support\Collection<int,\App\Models\Comment> $comments */
    /** @var bool $canPostComment */
    /** @var int $commentCooldownSeconds */
    /** @var bool $viewerCanCommanage */
    /** @var array<string,string> $hotMeter */
    /** @var string $descriptionHtml */
    /** @var string $technicalInfoHtml */
    /** @var string $customFieldsHtml */
    /** @var array{imdbId:int,url:string,posterUrl:string|null,rating:string,title:string,year:string|null,country:list<string>,genres:list<string>,directors:list<string>,creators:list<string>,cast:list<string>,plot:string|null,runtime:string|null,language:string|null,tagline:string|null}|null $imdbHero */
    /** @var \Illuminate\Support\Collection<int,\App\Models\Torrent> $otherCopies */
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

    @if ($postWriteBanner !== null)
        @php
            $bannerVariant = $postWriteBanner === 'existed' ? 'danger' : 'success';
            $bannerTitle = match ($postWriteBanner) {
                'uploaded' => 'Successfully uploaded.',
                'edited' => 'Successfully edited.',
                'existed' => 'This torrent has already been uploaded.',
            };
        @endphp
        <x-ui.alert :variant="$bannerVariant"
                    :title="$bannerTitle"
                    data-test-id="post-write-banner"
                    data-banner-type="{{ $postWriteBanner }}">
            @if ($postWriteBanner === 'uploaded')
                <p>
                    Remember to <strong>re-download</strong> the torrent file from the link above before seeding.
                </p>
            @endif
            @if ($returnto !== null && $postWriteBanner !== 'uploaded')
                <p>
                    <a href="{{ $returnto }}"
                       class="font-medium underline"
                       data-test-id="post-write-returnto">
                        Go back to where you came from
                    </a>
                </p>
            @endif
        </x-ui.alert>
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
            <span class="inline-flex items-baseline gap-1" data-test-id="promotion-badge">
                <x-ui.badge :variant="$promotionBadge[1]" size="sm">{{ $promotionBadge[0] }}</x-ui.badge>
                @if ($promotionSubtext)
                    <span class="text-xs text-zinc-500 dark:text-zinc-400" data-test-id="promotion-subtext">
                        {{ $promotionSubtext }}
                    </span>
                @endif
            </span>
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

    @if ($isAuthed && count($actionRow) > 0)
        <x-ui.card>
            <div class="flex flex-wrap items-center gap-2"
                 data-test-id="torrent-action-row">
                @foreach ($actionRow as $action)
                    <x-ui.button :href="$action['url']"
                                 :variant="$action['variant']"
                                 :title="$action['title']"
                                 data-test-id="{{ $action['id'] === 'download' ? 'download-btn' : 'action-' . $action['id'] }}"
                                 data-action="{{ $action['id'] }}">
                        {{ $action['label'] }}
                    </x-ui.button>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    @if ($isAuthed && $claimBlock !== null)
        <x-ui.card>
            <div class="flex flex-wrap items-center gap-3"
                 data-test-id="claim-block"
                 data-claim-count="{{ $claimBlock['claimCount'] }}"
                 data-claim-remaining="{{ $claimBlock['remainingSlots'] }}"
                 data-claim-state="{{ $claimBlock['hasClaimed'] ? 'claimed' : 'open' }}">
                <h2 class="basis-full text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    Claim torrent
                </h2>

                @if ($claimBlock['hasClaimed'])
                    <button type="button"
                            data-test-id="claim-button"
                            data-claim-state="claimed"
                            disabled
                            class="inline-flex items-center rounded border border-zinc-300 bg-zinc-100 px-3 py-1 text-sm font-medium text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                        Claimed
                    </button>
                @else
                    <button type="button"
                            wire:click="addClaim"
                            wire:confirm="Are you sure to claim this torrent?"
                            wire:loading.attr="disabled"
                            data-test-id="claim-button"
                            data-claim-state="open"
                            class="inline-flex items-center rounded border border-primary-500 bg-primary-50 px-3 py-1 text-sm font-medium text-primary-700 hover:bg-primary-100 disabled:opacity-50 dark:border-primary-400 dark:bg-primary-950/40 dark:text-primary-200 dark:hover:bg-primary-950/70">
                        Claim
                    </button>
                @endif

                <span class="text-sm text-zinc-600 dark:text-zinc-300" data-test-id="claim-info">
                    Already claimed by <b>{{ number_format($claimBlock['claimCount']) }}</b> users,
                    <b>{{ number_format($claimBlock['remainingSlots']) }}</b> place left.
                </span>

                <a href="{{ $claimBlock['detailsUrl'] }}"
                   class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-300"
                   data-test-id="claim-detail-link">
                    Claim detail
                </a>

                @error('claim')
                    <p class="basis-full text-sm text-rose-600 dark:text-rose-300" data-test-id="claim-error">
                        {{ $message }}
                    </p>
                @enderror

                @if ($claimFlash !== null)
                    <p class="basis-full text-sm text-emerald-600 dark:text-emerald-300" data-test-id="claim-flash">
                        {{ $claimFlash }}
                    </p>
                @endif
            </div>
        </x-ui.card>
    @endif

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

    @if ($tagsHtml !== '')
        <x-ui.card>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                Tags
            </h2>
            <div class="flex flex-wrap gap-2" data-test-id="torrent-tags">
                {!! $tagsHtml !!}
            </div>
        </x-ui.card>
    @endif

    @if ($uploaderBandwidth !== null)
        <x-ui.card>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                Uploader bandwidth
            </h2>
            <dl class="grid gap-4 sm:grid-cols-3" data-test-id="uploader-bandwidth">
                @if ($uploaderBandwidth['isp'] !== null)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">ISP</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100" data-test-id="uploader-isp">
                            {{ $uploaderBandwidth['isp'] }}
                        </dd>
                    </div>
                @endif
                @if ($uploaderBandwidth['up'] !== null)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Upload</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100" data-test-id="uploader-up">
                            {{ $uploaderBandwidth['up'] }}
                        </dd>
                    </div>
                @endif
                @if ($uploaderBandwidth['down'] !== null)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Download</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100" data-test-id="uploader-down">
                            {{ $uploaderBandwidth['down'] }}
                        </dd>
                    </div>
                @endif
            </dl>
        </x-ui.card>
    @endif

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

    @if ($customFieldsHtml !== '')
        <x-ui.card>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                Custom fields
            </h2>
            <div class="overflow-x-auto text-sm text-zinc-700 dark:text-zinc-200"
                 data-test-id="torrent-custom-fields">
                <table class="min-w-full">
                    <tbody>
                        {!! $customFieldsHtml !!}
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif

    @if ($imdbHero !== null)
        <x-ui.card>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                IMDb
            </h2>
            <div class="flex flex-col gap-4 text-sm text-zinc-700 dark:text-zinc-200 sm:flex-row"
                 data-test-id="torrent-imdb-hero">
                @if ($imdbHero['posterUrl'] !== null)
                    <div class="shrink-0">
                        <img class="h-auto w-32 rounded border border-zinc-200 object-cover dark:border-zinc-700"
                             src="{{ $imdbHero['posterUrl'] }}"
                             alt="IMDb poster for {{ $imdbHero['title'] }}"
                             loading="lazy" />
                    </div>
                @endif
                <div class="flex flex-1 flex-col gap-2">
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <a class="text-base font-semibold text-blue-600 hover:underline dark:text-blue-400"
                           href="{{ $imdbHero['url'] }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           data-test-id="torrent-imdb-link">{{ $imdbHero['title'] !== '' ? $imdbHero['title'] : 'tt'.$imdbHero['imdbId'] }}</a>
                        @if ($imdbHero['year'] !== null)
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">({{ $imdbHero['year'] }})</span>
                        @endif
                        <span class="ml-auto inline-flex items-center gap-1 rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-200"
                              data-test-id="torrent-imdb-rating">
                            <span aria-hidden="true">&#9733;</span>
                            <span>{{ $imdbHero['rating'] }}{{ $imdbHero['rating'] !== 'N/A' ? '/10' : '' }}</span>
                        </span>
                    </div>
                    @if ($imdbHero['tagline'] !== null)
                        <p class="italic text-zinc-500 dark:text-zinc-400">{{ $imdbHero['tagline'] }}</p>
                    @endif
                    @if ($imdbHero['plot'] !== null)
                        <p class="text-sm text-zinc-700 dark:text-zinc-200">{{ $imdbHero['plot'] }}</p>
                    @endif
                    <dl class="grid grid-cols-[max-content_1fr] gap-x-3 gap-y-1 text-xs text-zinc-600 dark:text-zinc-300">
                        @if ($imdbHero['genres'] !== [])
                            <dt class="font-semibold text-zinc-500 dark:text-zinc-400">Genres</dt>
                            <dd>{{ implode(', ', $imdbHero['genres']) }}</dd>
                        @endif
                        @if ($imdbHero['country'] !== [])
                            <dt class="font-semibold text-zinc-500 dark:text-zinc-400">Country</dt>
                            <dd>{{ implode(', ', $imdbHero['country']) }}</dd>
                        @endif
                        @if ($imdbHero['language'] !== null)
                            <dt class="font-semibold text-zinc-500 dark:text-zinc-400">Language</dt>
                            <dd>{{ $imdbHero['language'] }}</dd>
                        @endif
                        @if ($imdbHero['runtime'] !== null)
                            <dt class="font-semibold text-zinc-500 dark:text-zinc-400">Runtime</dt>
                            <dd>{{ $imdbHero['runtime'] }}</dd>
                        @endif
                        @if ($imdbHero['directors'] !== [])
                            <dt class="font-semibold text-zinc-500 dark:text-zinc-400">Director</dt>
                            <dd>{{ implode(', ', $imdbHero['directors']) }}</dd>
                        @elseif ($imdbHero['creators'] !== [])
                            <dt class="font-semibold text-zinc-500 dark:text-zinc-400">Creator</dt>
                            <dd>{{ implode(', ', $imdbHero['creators']) }}</dd>
                        @endif
                        @if ($imdbHero['cast'] !== [])
                            <dt class="font-semibold text-zinc-500 dark:text-zinc-400">Cast</dt>
                            <dd>{{ implode(', ', $imdbHero['cast']) }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </x-ui.card>
    @endif

    @if ($otherCopies->isNotEmpty())
        <x-ui.card>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                Other copies ({{ number_format($otherCopies->count()) }})
            </h2>
            <div class="overflow-x-auto" data-test-id="torrent-other-copies">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs uppercase text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="px-2 py-1 font-medium">Type</th>
                            <th class="px-2 py-1 font-medium">Name</th>
                            <th class="px-2 py-1 font-medium text-right">Size</th>
                            <th class="px-2 py-1 font-medium">Added</th>
                            <th class="px-2 py-1 font-medium text-right">S</th>
                            <th class="px-2 py-1 font-medium text-right">L</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach ($otherCopies as $copy)
                            @php
                                $copyCategory = $copy->basic_category;
                                $copyAdded = $copy->added instanceof \Illuminate\Support\Carbon ? $copy->added->format('Y-m-d H:i') : (string) $copy->added;
                            @endphp
                            <tr class="text-zinc-700 dark:text-zinc-200">
                                <td class="px-2 py-1 align-top">
                                    @if ($copyCategory !== null)
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $copyCategory->name }}</span>
                                    @endif
                                </td>
                                <td class="px-2 py-1 align-top">
                                    <a class="text-blue-600 hover:underline dark:text-blue-400"
                                       href="/torrent/{{ (int) $copy->id }}">{{ $copy->name }}</a>
                                </td>
                                <td class="px-2 py-1 align-top text-right tabular-nums">{{ \App\Livewire\TorrentBrowse::formatBytes((int) $copy->size) }}</td>
                                <td class="px-2 py-1 align-top text-xs text-zinc-500 dark:text-zinc-400">{{ $copyAdded }}</td>
                                <td class="px-2 py-1 align-top text-right tabular-nums text-emerald-600 dark:text-emerald-400">{{ number_format((int) $copy->seeders) }}</td>
                                <td class="px-2 py-1 align-top text-right tabular-nums text-rose-600 dark:text-rose-400">{{ number_format((int) $copy->leechers) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
            <section id="{{ $key }}" class="mt-4 first:mt-0 scroll-mt-4" data-test-id="peers-{{ $key }}">
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
                    @php
                        $canEdit = $isAuthed && ($isOwnComment || $viewerCanCommanage);
                        $canDelete = $viewerCanCommanage;
                        $isEditing = $editingCommentId === (int) $comment->id;
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
                        @if ($isEditing)
                            <div class="mt-2" data-test-id="comment-edit-form" data-comment-id="{{ $comment->id }}">
                                <textarea
                                    wire:model="editingBody"
                                    rows="3"
                                    class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                                    data-test-id="comment-edit-body"></textarea>
                                @error('editingBody')
                                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400" data-test-id="comment-edit-error">{{ $message }}</p>
                                @enderror
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <x-ui.button
                                        wire:click="updateComment({{ (int) $comment->id }})"
                                        variant="primary"
                                        size="sm"
                                        data-test-id="comment-edit-save">
                                        Save
                                    </x-ui.button>
                                    <x-ui.button
                                        wire:click="cancelEditComment"
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        data-test-id="comment-edit-cancel">
                                        Cancel
                                    </x-ui.button>
                                </div>
                            </div>
                        @else
                            <div class="mt-2 break-words text-sm text-zinc-800 dark:text-zinc-100" data-test-id="comment-body">
                                {!! \App\Support\BbcodeRenderer::toHtml((string) $comment->text) !!}
                            </div>
                            @if ($editedTs !== null)
                                <p class="mt-2 text-xs italic text-zinc-500 dark:text-zinc-400" data-test-id="comment-edited">
                                    Edited {{ date('Y-m-d H:i', $editedTs) }}
                                </p>
                            @endif
                            @if ($canEdit || $canDelete)
                                <div class="mt-2 flex flex-wrap items-center gap-2" data-test-id="comment-actions">
                                    @if ($canEdit)
                                        <button
                                            type="button"
                                            wire:click="startEditComment({{ (int) $comment->id }})"
                                            class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                                            data-test-id="comment-edit-btn">
                                            Edit
                                        </button>
                                    @endif
                                    @if ($canDelete)
                                        <button
                                            type="button"
                                            wire:click="deleteComment({{ (int) $comment->id }})"
                                            wire:confirm="Delete this comment?"
                                            class="text-xs font-medium text-rose-600 hover:underline dark:text-rose-400"
                                            data-test-id="comment-delete-btn">
                                            Delete
                                        </button>
                                    @endif
                                </div>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($isAuthed)
            <div class="mt-6 border-t border-zinc-200 pt-4 dark:border-zinc-800" data-test-id="comment-reply">
                @if (! $canPostComment)
                    <p class="text-sm italic text-zinc-500 dark:text-zinc-400" data-test-id="comment-reply-disabled">
                        Your account is parked. Comments are disabled.
                    </p>
                @else
                    <label for="new-comment-body" class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
                        Reply
                    </label>
                    <textarea
                        id="new-comment-body"
                        wire:model="newCommentBody"
                        rows="3"
                        placeholder="Write a comment…"
                        class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                        data-test-id="comment-reply-body"></textarea>
                    @error('newCommentBody')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400" data-test-id="comment-reply-error">{{ $message }}</p>
                    @enderror
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <x-ui.button
                            wire:click="postComment"
                            variant="primary"
                            size="sm"
                            data-test-id="comment-reply-submit">
                            Post comment
                        </x-ui.button>
                        @if ($commentCooldownSeconds > 0)
                            <span class="text-xs italic text-zinc-500 dark:text-zinc-400" data-test-id="comment-reply-cooldown">
                                Wait {{ $commentCooldownSeconds }}s before posting again.
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </x-ui.card>

    <div class="flex flex-wrap items-center gap-2">
        <x-ui.button href="/browse" variant="secondary">
            ← Back to browse
        </x-ui.button>
    </div>
</div>
