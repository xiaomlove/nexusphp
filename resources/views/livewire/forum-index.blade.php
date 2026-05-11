<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">Forum</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Modern Livewire view of the discussion forums. Posting and topic discussion still happen on the legacy page.
            </p>
        </div>
        <a
            href="/forums.php"
            class="inline-flex items-center gap-1 self-start rounded-md border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
            title="Open the legacy forum page (post, reply, edit)"
        >
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14L21 3m0 0v7m0-7h-7M5 5h6v6"/>
            </svg>
            Open legacy forum
        </a>
    </div>

    @if ($overforums->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-base font-medium text-zinc-700 dark:text-zinc-300">No forums available.</p>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">You may not have permission to view any sections.</p>
        </div>
    @endif

    @foreach ($overforums as $over)
        @php
            $forums = $forumsByOverforum->get($over->id, collect());
        @endphp
        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <header class="flex items-baseline justify-between border-b border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/40">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">{{ $over->name }}</h2>
                @if (! empty($over->description))
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $over->description }}</span>
                @endif
            </header>

            @if ($forums->isEmpty())
                <p class="p-4 text-sm italic text-zinc-500 dark:text-zinc-400">No forums in this section yet.</p>
            @else
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($forums as $forum)
                        @php
                            $last = $lastPosts[$forum->id] ?? null;
                        @endphp
                        <li>
                            <a
                                href="/forum/{{ $forum->id }}"
                                class="grid grid-cols-12 items-center gap-3 px-4 py-3 transition hover:bg-zinc-50 dark:hover:bg-zinc-800"
                            >
                                <div class="col-span-12 sm:col-span-6">
                                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $forum->name }}</p>
                                    @if (! empty($forum->description))
                                        <p class="mt-0.5 line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $forum->description }}</p>
                                    @endif
                                </div>
                                <div class="col-span-6 text-xs text-zinc-500 dark:text-zinc-400 sm:col-span-2 sm:text-center">
                                    <span class="block font-mono">{{ \App\Livewire\ForumIndex::counts($forum->topiccount, $forum->postcount) }}</span>
                                    <span class="text-[10px] uppercase tracking-wider">topics / posts</span>
                                </div>
                                <div class="col-span-6 text-xs text-zinc-500 dark:text-zinc-400 sm:col-span-4">
                                    @if ($last)
                                        <p class="line-clamp-1 font-medium text-zinc-700 dark:text-zinc-300">
                                            <span class="text-zinc-500 dark:text-zinc-400">in</span>
                                            <span>{{ $last['topic_subject'] }}</span>
                                        </p>
                                        <p class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                            @if ($last['username'])
                                                by <a href="/userdetails.php?id={{ $last['user_id'] }}" class="font-medium text-zinc-700 hover:text-primary-600 dark:text-zinc-300 dark:hover:text-primary-400" wire:navigate.hover>{{ $last['username'] }}</a>
                                            @else
                                                by anonymous
                                            @endif
                                            @if ($last['added'])
                                                ·
                                                <time datetime="{{ \Carbon\Carbon::parse($last['added'])->toIso8601String() }}" title="{{ \Carbon\Carbon::parse($last['added'])->format('Y-m-d H:i') }}">
                                                    {{ \Carbon\Carbon::parse($last['added'])->diffForHumans() }}
                                                </time>
                                            @endif
                                        </p>
                                    @else
                                        <p class="italic text-zinc-400 dark:text-zinc-600">No posts yet</p>
                                    @endif
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach
</div>
