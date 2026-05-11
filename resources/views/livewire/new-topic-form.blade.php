<div class="space-y-6">
    <div>
        <nav class="text-xs text-zinc-500 dark:text-zinc-400">
            <a href="/forum" class="hover:text-zinc-700 dark:hover:text-zinc-200">Forum</a>
            <span class="mx-1">/</span>
            <a href="/forum/{{ $forum->id }}" class="hover:text-zinc-700 dark:hover:text-zinc-200">{{ $forum->name }}</a>
            <span class="mx-1">/</span>
            <span>New topic</span>
        </nav>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
            Start a new topic
        </h1>
        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
            Posting in <strong>{{ $forum->name }}</strong>.
        </p>
    </div>

    <form wire:submit="submit" class="space-y-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        @if ($errorMessage)
            <div role="alert" class="rounded border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-700 dark:bg-rose-950 dark:text-rose-200">
                {{ $errorMessage }}
            </div>
        @endif

        <div>
            <label for="topic-subject" class="block text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">Subject</label>
            <input
                id="topic-subject"
                type="text"
                wire:model="subject"
                maxlength="255"
                autocomplete="off"
                class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
            >
            @error('subject')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="topic-body" class="block text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">Body</label>
            <textarea
                id="topic-body"
                wire:model="body"
                rows="10"
                placeholder="Write your post… BBCode supported (b, i, u, url, img, quote, code, list)."
                class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
            ></textarea>
            @error('body')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between border-t border-slate-200 pt-3 dark:border-slate-700">
            <a
                href="/forum/{{ $forum->id }}"
                class="text-xs text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-100"
            >Cancel</a>
            <button
                type="submit"
                class="inline-flex items-center rounded bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-slate-900"
                wire:loading.attr="disabled"
                wire:target="submit"
            >
                <span wire:loading.remove wire:target="submit">Post topic</span>
                <span wire:loading wire:target="submit">Posting…</span>
            </button>
        </div>
    </form>
</div>
