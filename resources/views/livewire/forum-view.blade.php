<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <nav class="text-xs text-zinc-500 dark:text-zinc-400">
                <a href="/forum" class="hover:text-zinc-700 dark:hover:text-zinc-200">Forum</a>
                <span class="mx-1">/</span>
                <span>{{ $forum->name }}</span>
            </nav>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{{ $forum->name }}</h1>
            @if (! empty($forum->description))
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $forum->description }}</p>
            @endif
        </div>
        <div class="flex flex-col gap-2 self-start sm:flex-row">
            @php($_user = auth('nexus-web')->user())
            @php($_canCreate = $_user && (int) ($_user->class ?? 0) >= max((int) $forum->minclassread, (int) $forum->minclasswrite, (int) $forum->minclasscreate))
            @if ($_canCreate)
                <a
                    href="/forum/{{ $forum->id }}/new"
                    class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                    title="Start a new topic in this forum"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    New topic
                </a>
            @endif
            <a
                href="/forums.php?action=viewforum&forumid={{ $forum->id }}"
                class="inline-flex items-center gap-1 rounded-md border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                title="Open the legacy forum page (edit, moderation tools)"
            >
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 14L21 3m0 0v7m0-7h-7M5 5h6v6"/>
                </svg>
                Legacy view
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="grid gap-3 md:grid-cols-12">
            <div class="md:col-span-7">
                <label for="search" class="mb-1 block text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                    Search topics
                </label>
                <input
                    id="search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Topic subject…"
                    class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                >
            </div>
            <div class="md:col-span-5">
                <label for="sort" class="mb-1 block text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                    Sort by
                </label>
                <select
                    id="sort"
                    wire:model.live="sort"
                    class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                >
                    @foreach ($sortOptions as $option)
                        <option value="{{ $option['key'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @if ($search !== '' || $sort !== 'lastpost-desc')
            <div class="mt-3 text-xs">
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

    @if ($topics->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-base font-medium text-zinc-700 dark:text-zinc-300">No topics found.</p>
            @if ($search !== '')
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Try a different search term.</p>
            @else
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">This forum has no topics yet.</p>
            @endif
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
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
                                @if ($isSticky)
                                    <span class="mt-0.5 inline-flex shrink-0 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-800 dark:bg-amber-900/40 dark:text-amber-300" title="Pinned">Pin</span>
                                @endif
                                @if ($isLocked)
                                    <span class="mt-0.5 inline-flex shrink-0 rounded bg-zinc-200 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200" title="Locked">Lock</span>
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
        </div>

        <div class="text-xs text-zinc-500 dark:text-zinc-400">
            {{ $topics->links() }}
        </div>
    @endif
</div>
