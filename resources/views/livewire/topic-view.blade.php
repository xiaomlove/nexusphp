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
                    <x-ui.badge variant="warning" size="sm" title="Pinned">Pin</x-ui.badge>
                @endif
                @if ($topic->locked === 'yes')
                    <x-ui.badge variant="neutral" size="sm" title="Locked">Lock</x-ui.badge>
                @endif
                <span class="break-words">{{ $topic->subject }}</span>
            </h1>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                {{ number_format((int) $topic->views) }} views
            </p>
        </div>
        <x-ui.button href="/forums.php?action=viewtopic&topicid={{ $topic->id }}" variant="secondary" size="sm" title="Open the legacy view (BBCode-rendered, reply, edit)">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14L21 3m0 0v7m0-7h-7M5 5h6v6"/>
            </svg>
            Reply / formatted view
        </x-ui.button>
    </div>

    @if ($canModerate ?? false)
        <x-ui.alert variant="warning" title="Topic actions">
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="toggleSticky"
                    class="rounded border border-amber-300 bg-white px-2 py-1 text-xs text-amber-800 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200 dark:hover:bg-amber-900/60"
                    title="{{ $topic->sticky === 'yes' ? 'Unpin this topic' : 'Pin this topic to the top of the forum' }}"
                >{{ $topic->sticky === 'yes' ? 'Unpin' : 'Pin' }}</button>
                <button
                    type="button"
                    wire:click="toggleLocked"
                    class="rounded border border-amber-300 bg-white px-2 py-1 text-xs text-amber-800 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200 dark:hover:bg-amber-900/60"
                    title="{{ $topic->locked === 'yes' ? 'Unlock this topic' : 'Lock this topic from new replies' }}"
                >{{ $topic->locked === 'yes' ? 'Unlock' : 'Lock' }}</button>

                <label class="flex items-center gap-1 text-xs">
                    Highlight
                    <select
                        wire:change="setHlColor($event.target.value)"
                        class="rounded border border-amber-300 bg-white px-1.5 py-1 text-xs text-amber-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-100"
                    >
                        @foreach ($hlColors as $value => $label)
                            <option value="{{ $value }}" @selected((int) $topic->hlcolor === (int) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <button
                    type="button"
                    wire:click="$toggle('showMoveDialog')"
                    class="rounded border border-amber-300 bg-white px-2 py-1 text-xs text-amber-800 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200 dark:hover:bg-amber-900/60"
                >Move…</button>
            </div>

            @if ($showMoveDialog)
                <div class="mt-3 flex flex-wrap items-end gap-2">
                    <label class="text-xs">
                        <span class="block mb-1">Destination forum</span>
                        <select
                            wire:model="moveTargetForumId"
                            class="block rounded border border-amber-300 bg-white px-2 py-1 text-xs text-amber-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-100"
                        >
                            <option value="0">— pick a forum —</option>
                            @foreach ($availableForums as $f)
                                <option value="{{ $f->id }}">{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button
                        type="button"
                        wire:click="moveTopic"
                        class="rounded bg-amber-600 px-3 py-1 text-xs font-semibold text-white shadow-sm hover:bg-amber-700"
                    >Move topic</button>
                    <button
                        type="button"
                        wire:click="$set('showMoveDialog', false)"
                        class="rounded border border-amber-300 px-2 py-1 text-xs text-amber-800 hover:bg-amber-100 dark:border-amber-700 dark:text-amber-200 dark:hover:bg-amber-900/40"
                    >Cancel</button>
                </div>
            @endif

            @if ($modError)
                <p class="mt-2 text-xs text-rose-700 dark:text-rose-300">{{ $modError }}</p>
            @endif
        </x-ui.alert>
    @endif

    @if ($deleteError)
        <x-ui.alert variant="danger">{{ $deleteError }}</x-ui.alert>
    @endif

    @if ($authorFilter > 0)
        <x-ui.alert variant="info">
            <div class="flex items-center gap-2">
                <span>Showing posts by user #{{ $authorFilter }} only.</span>
                <x-ui.button type="button" wire:click="clearAuthorFilter" variant="secondary" size="sm">
                    Show all
                </x-ui.button>
            </div>
        </x-ui.alert>
    @endif

    @if ($posts->isEmpty())
        <x-ui.empty-state title="No posts in this topic.">
            This shouldn't happen — try the legacy view.
        </x-ui.empty-state>
    @else
        <ol class="space-y-4">
            @foreach ($posts as $post)
                <x-ui.card padding="none" class="overflow-hidden">
                    <li
                        id="post-{{ $post->id }}"
                        class="flex flex-col gap-3 sm:flex-row"
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
                            <header class="mb-3 flex flex-wrap items-baseline justify-between gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                <div class="flex flex-wrap items-baseline gap-2">
                                    <a href="#post-{{ $post->id }}" class="font-mono hover:text-zinc-700 dark:hover:text-zinc-200">#{{ $post->id }}</a>
                                    @if ($post->added)
                                        <time datetime="{{ $post->added }}" title="{{ $post->added }}">
                                            {{ \Carbon\Carbon::parse($post->added)->diffForHumans() }}
                                        </time>
                                    @endif
                                    @if ($post->editdate && $post->editedby)
                                        <span class="italic">(edited {{ \Carbon\Carbon::parse($post->editdate)->diffForHumans() }})</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1">
                                    <x-ui.button type="button" wire:click="quote({{ (int) $post->id }})" variant="secondary" size="sm" title="Quote this post in your reply">
                                        Quote
                                    </x-ui.button>
                                    @if ($editable[(int) $post->id] ?? false)
                                        <x-ui.button type="button" wire:click="startEditing({{ (int) $post->id }})" variant="secondary" size="sm" title="Edit this post">
                                            Edit
                                        </x-ui.button>
                                    @endif
                                    @if ($deletable[(int) $post->id] ?? false)
                                        <x-ui.button
                                            type="button"
                                            wire:click="deletePost({{ (int) $post->id }})"
                                            wire:confirm="Delete this post? This cannot be undone."
                                            variant="danger"
                                            size="sm"
                                            title="Delete this post"
                                        >Delete</x-ui.button>
                                    @endif
                                </div>
                            </header>
                            @if ($editingPostId === (int) $post->id)
                                <livewire:edit-post-form :post-id="(int) $post->id" :key="'edit-post-'.$post->id" />
                            @else
                                <div class="prose prose-sm max-w-none break-words text-zinc-800 dark:prose-invert dark:text-zinc-200">
                                    {!! \App\Livewire\TopicView::renderBody($post->body) !!}
                                </div>
                            @endif
                        </article>
                    </li>
                </x-ui.card>
            @endforeach
        </ol>

        <div class="text-xs text-zinc-500 dark:text-zinc-400">
            {{ $posts->links() }}
        </div>
    @endif

    <livewire:reply-form :forum-id="$forum->id" :topic-id="$topic->id" :key="'reply-form-'.$topic->id" />
</div>
