<div class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
    <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
            Quick reply
        </h3>
    </div>

    @if (! $canPost)
        <div class="px-4 py-4 text-sm text-slate-600 dark:text-slate-400">
            {{ $lockedReason }}
        </div>
    @else
        <form wire:submit="submit" class="px-4 py-3">
            @if ($errorMessage)
                <div role="alert" class="mb-3 rounded border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-700 dark:bg-rose-950 dark:text-rose-200">
                    {{ $errorMessage }}
                </div>
            @endif

            <label for="reply-body" class="sr-only">Reply body</label>
            <textarea
                id="reply-body"
                wire:model="body"
                rows="6"
                placeholder="Write a reply… BBCode supported (b, i, u, url, img, quote, code, list)."
                class="block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
            ></textarea>

            @error('body')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror

            <div class="mt-3 flex items-center justify-between">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Posts are subject to a 10-second flood guard.
                </p>
                <button
                    type="submit"
                    class="inline-flex items-center rounded bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-slate-900"
                    wire:loading.attr="disabled"
                    wire:target="submit"
                >
                    <span wire:loading.remove wire:target="submit">Post reply</span>
                    <span wire:loading wire:target="submit">Posting…</span>
                </button>
            </div>
        </form>
    @endif
</div>
