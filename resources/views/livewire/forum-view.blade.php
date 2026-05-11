@php
    $_user = auth('nexus-web')->user();
    $_canCreate = $_user && (int) ($_user->class ?? 0) >= max((int) $forum->minclassread, (int) $forum->minclasswrite, (int) $forum->minclasscreate);
    $_filtersDirty = $search !== '' || $sort !== 'lastpost-desc';
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <nav class="text-xs text-zinc-500 dark:text-zinc-400">
                <a href="/forum" class="hover:text-zinc-700 dark:hover:text-zinc-200">Forum</a>
                <span class="mx-1">/</span>
                <span>{{ $forum->name }}</span>
            </nav>
            <x-ui.page-header :title="$forum->name">
                @if (! empty($forum->description))
                    <x-slot:description>{{ $forum->description }}</x-slot:description>
                @endif
            </x-ui.page-header>
        </div>
        <div class="flex flex-col gap-2 self-start sm:flex-row">
            @if ($_canCreate)
                <x-ui.button href="/forum/{{ $forum->id }}/new" size="sm" title="Start a new topic in this forum">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    New topic
                </x-ui.button>
            @endif
            <x-ui.button href="/forums.php?action=viewforum&forumid={{ $forum->id }}" variant="secondary" size="sm" title="Open the legacy forum page (edit, moderation tools)">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 14L21 3m0 0v7m0-7h-7M5 5h6v6"/>
                </svg>
                Legacy view
            </x-ui.button>
        </div>
    </div>

    <x-ui.card>
        <div class="grid gap-3 md:grid-cols-12">
            <div class="md:col-span-7">
                <x-ui.form-label for="search">Search topics</x-ui.form-label>
                <x-ui.form-input
                    id="search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Topic subject…"
                />
            </div>
            <div class="md:col-span-5">
                <x-ui.form-label for="sort">Sort by</x-ui.form-label>
                <x-ui.form-select id="sort" wire:model.live="sort">
                    @foreach ($sortOptions as $option)
                        <option value="{{ $option['key'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </x-ui.form-select>
            </div>
        </div>
        @if ($_filtersDirty)
            <div class="mt-3 text-xs">
                <x-ui.button type="button" wire:click="clearFilters" variant="secondary" size="sm">
                    Clear filters
                </x-ui.button>
            </div>
        @endif
    </x-ui.card>

    @if ($canModerate ?? false)
        <x-ui.alert variant="warning" title="Bulk actions">
            <div class="mt-2 flex flex-wrap items-end gap-2">
                <span class="text-xs">{{ count($selectedTopicIds) }} selected</span>
                <label class="flex items-center gap-1 text-xs">
                    Action
                    <select wire:model.live="bulkAction" class="rounded border border-amber-300 bg-white px-2 py-1 text-xs text-zinc-900 shadow-sm dark:border-amber-700 dark:bg-zinc-900 dark:text-zinc-100">
                        <option value="">— pick —</option>
                        <option value="sticky-on">Pin</option>
                        <option value="sticky-off">Unpin</option>
                        <option value="lock-on">Lock</option>
                        <option value="lock-off">Unlock</option>
                        <option value="hlcolor">Set highlight…</option>
                        <option value="move">Move to forum…</option>
                    </select>
                </label>
                @if ($bulkAction === 'hlcolor')
                    <label class="flex items-center gap-1 text-xs">
                        Highlight
                        <select wire:model="bulkHlColor" class="rounded border border-amber-300 bg-white px-2 py-1 text-xs text-zinc-900 shadow-sm dark:border-amber-700 dark:bg-zinc-900 dark:text-zinc-100">
                            @foreach ($hlColors as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                @if ($bulkAction === 'move')
                    <label class="flex items-center gap-1 text-xs">
                        Destination
                        <select wire:model="bulkTargetForumId" class="rounded border border-amber-300 bg-white px-2 py-1 text-xs text-zinc-900 shadow-sm dark:border-amber-700 dark:bg-zinc-900 dark:text-zinc-100">
                            <option value="0">— pick a forum —</option>
                            @foreach ($availableForums as $f)
                                <option value="{{ $f->id }}">{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <button
                    type="button"
                    wire:click="applyBulkAction"
                    class="rounded bg-amber-600 px-3 py-1 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
                    @disabled(empty($selectedTopicIds) || $bulkAction === '')
                >Apply</button>
            </div>
            @if ($bulkNotice)
                <p class="mt-2 text-xs text-emerald-700 dark:text-emerald-300">{{ $bulkNotice }}</p>
            @endif
            @if ($bulkError)
                <p class="mt-2 text-xs text-rose-700 dark:text-rose-300">{{ $bulkError }}</p>
            @endif
        </x-ui.alert>
    @endif

    @if ($topics->isEmpty())
        <x-ui.empty-state title="No topics found.">
            @if ($search !== '')
                Try a different search term.
            @else
                This forum has no topics yet.
            @endif
        </x-ui.empty-state>
    @else
        <x-ui.card padding="none" class="overflow-hidden">
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($topics as $topic)
                    @php
                        $isSticky = $topic->sticky === 'yes';
                        $isLocked = $topic->locked === 'yes';
                        $last = $lastPostMeta[(int) $topic->lastpost] ?? null;
                    @endphp
                    <li class="grid grid-cols-12 items-center gap-3 px-4 py-3 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <div class="col-span-12 sm:col-span-6">
                            <div class="flex items-start gap-2">
                                @if ($canModerate ?? false)
                                    <input
                                        type="checkbox"
                                        wire:model.live="selectedTopicIds"
                                        value="{{ $topic->id }}"
                                        class="mt-0.5 h-3.5 w-3.5 shrink-0 rounded border-amber-400 text-amber-600 focus:ring-amber-500"
                                        title="Select for bulk moderation"
                                    >
                                @endif
                                @if ($isSticky)
                                    <x-ui.badge variant="warning" size="sm" title="Pinned">Pin</x-ui.badge>
                                @endif
                                @if ($isLocked)
                                    <x-ui.badge variant="neutral" size="sm" title="Locked">Lock</x-ui.badge>
                                @endif
                                <div class="min-w-0">
                                    <a
                                        href="/forum/{{ $forum->id }}/topic/{{ $topic->id }}"
                                        class="block text-sm font-semibold text-zinc-900 hover:text-primary-600 dark:text-zinc-100 dark:hover:text-primary-400"
                                    >
                                        <span class="line-clamp-2">{{ $topic->subject }}</span>
                                    </a>
                                    <p class="mt-0.5 text-[11px] text-zinc-500 dark:text-zinc-400">
                                        by
                                        <a href="/userdetails.php?id={{ $topic->userid }}" class="hover:text-zinc-700 dark:hover:text-zinc-200">user #{{ $topic->userid }}</a>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-span-6 text-xs text-zinc-500 dark:text-zinc-400 sm:col-span-2 sm:text-center">
                            <span class="block font-mono">{{ \App\Livewire\ForumView::formatCounts($topic->views) }}</span>
                            <span class="text-[10px] uppercase tracking-wider">views</span>
                        </div>
                        <div class="col-span-6 text-xs text-zinc-500 dark:text-zinc-400 sm:col-span-4">
                            @if ($last)
                                <p class="line-clamp-1 font-medium text-zinc-700 dark:text-zinc-300">
                                    @if ($last['username'])
                                        by <a href="/userdetails.php?id={{ $last['userid'] }}" class="hover:text-zinc-900 dark:hover:text-zinc-100">{{ $last['username'] }}</a>
                                    @else
                                        <span class="italic">anonymous</span>
                                    @endif
                                </p>
                                @if ($last['added'])
                                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                        <time datetime="{{ $last['added'] }}" title="{{ $last['added'] }}">
                                            {{ \Carbon\Carbon::parse($last['added'])->diffForHumans() }}
                                        </time>
                                    </p>
                                @endif
                            @else
                                <p class="italic">No replies yet</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        <div class="text-xs text-zinc-500 dark:text-zinc-400">
            {{ $topics->links() }}
        </div>
    @endif
</div>
