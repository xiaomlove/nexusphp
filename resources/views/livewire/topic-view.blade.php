<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <nav class="text-xs text-zinc-500 dark:text-zinc-400">
                <a href="/forum" class="hover:text-zinc-700 dark:hover:text-zinc-200">Forum</a>
                <span class="mx-1">/</span>
                <a href="/forum/{{ $forum->id }}" class="hover:text-zinc-700 dark:hover:text-zinc-200">{{ $forum->name }}</a>
                <span class="mx-1">/</span>
                <span>{{ Str::limit($topic->subject, 60) }}</span>
            </nav>
            <h1 class="mt-1 flex flex-wrap items-center gap-2 text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                @if ($topic->sticky === 'yes')
                    <span class="inline-flex shrink-0 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-800 dark:bg-amber-900/40 dark:text-amber-300" title="Pinned">Pin</span>
                @endif
                @if ($topic->locked === 'yes')
                    <span class="inline-flex shrink-0 rounded bg-zinc-200 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200" title="Locked">Lock</span>
                @endif
                <span class="break-words">{{ $topic->subject }}</span>
            </h1>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                {{ number_format((int) $topic->views) }} views
            </p>
        </div>
        <a
            href="/forums.php?action=viewtopic&topicid={{ $topic->id }}"
            class="inline-flex items-center gap-1 self-start rounded-md border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
            title="Open the legacy view (BBCode-rendered, reply, edit)"
        >
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14L21 3m0 0v7m0-7h-7M5 5h6v6"/>
            </svg>
            Reply / formatted view
        </a>
    </div>

    @if ($authorFilter > 0)
        <div class="flex items-center gap-2 rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 text-xs dark:border-primary-900/50 dark:bg-primary-900/20">
            <span class="text-primary-800 dark:text-primary-300">Showing posts by user #{{ $authorFilter }} only.</span>
            <button
                type="button"
                wire:click="clearAuthorFilter"
                class="rounded-md border border-primary-300 px-2 py-0.5 text-primary-800 hover:bg-primary-100 dark:border-primary-700 dark:text-primary-200 dark:hover:bg-primary-900/40"
            >Show all</button>
        </div>
    @endif

    @if ($posts->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-base font-medium text-zinc-700 dark:text-zinc-300">No posts in this topic.</p>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">This shouldn't happen — try the legacy view.</p>
        </div>
    @else
        <ol class="space-y-4">
            @foreach ($posts as $post)
                <li
                    id="post-{{ $post->id }}"
                    class="flex flex-col gap-3 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:flex-row"
                >
                    <aside class="border-b border-zinc-100 bg-zinc-50 p-4 text-xs dark:border-zinc-800 dark:bg-zinc-950/40 sm:w-48 sm:shrink-0 sm:border-b-0 sm:border-r">
                        <div class="flex items-center gap-2 sm:flex-col sm:items-start sm:gap-1">
                            <p class="font-semibold text-zinc-900 dark:text-zinc-100">
                                @if ($post->author_username)
                                    <a href="/userdetails.php?id={{ $post->userid }}" class="hover:text-primary-600 dark:hover:text-primary-400">{{ $post->author_username }}</a>
                                @else
                                    <span class="italic text-zinc-500 dark:text-zinc-400">deleted user</span>
                                @endif
                            </p>
                            @if ($authorFilter !== (int) $post->userid && $post->userid)
                                <button
                                    type="button"
                                    wire:click="$set('authorFilter', {{ (int) $post->userid }})"
                                    class="text-[11px] text-zinc-500 hover:text-primary-600 dark:text-zinc-400 dark:hover:text-primary-400"
                                >Only this user</button>
                            @endif
                        </div>
                    </aside>
                    <article class="min-w-0 flex-1 p-4">
                        <header class="mb-3 flex flex-wrap items-baseline gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                            <a href="#post-{{ $post->id }}" class="font-mono hover:text-zinc-700 dark:hover:text-zinc-200">#{{ $post->id }}</a>
                            @if ($post->added)
                                <time datetime="{{ $post->added }}" title="{{ $post->added }}">
                                    {{ \Carbon\Carbon::parse($post->added)->diffForHumans() }}
                                </time>
                            @endif
                            @if ($post->editdate && $post->editedby)
                                <span class="italic">(edited {{ \Carbon\Carbon::parse($post->editdate)->diffForHumans() }})</span>
                            @endif
                        </header>
                        <div class="prose prose-sm max-w-none break-words text-zinc-800 dark:prose-invert dark:text-zinc-200">
                            {!! \App\Livewire\TopicView::renderBody($post->body) !!}
                        </div>
                    </article>
                </li>
            @endforeach
        </ol>

        <div class="text-xs text-zinc-500 dark:text-zinc-400">
            {{ $posts->links() }}
        </div>
    @endif
</div>
