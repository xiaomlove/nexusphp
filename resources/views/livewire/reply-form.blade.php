<x-ui.card padding="none" class="mt-6 overflow-hidden">
    <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
            Quick reply
        </h3>
    </div>

    @if (! $canPost)
        <div class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
            {{ $lockedReason }}
        </div>
    @else
        <form wire:submit="submit" class="px-4 py-3">
            @if ($errorMessage)
                <div class="mb-3">
                    <x-ui.alert variant="danger">{{ $errorMessage }}</x-ui.alert>
                </div>
            @endif

            <x-ui.form-label for="reply-body" class="sr-only">Reply body</x-ui.form-label>
            <textarea
                id="reply-body"
                wire:model="body"
                rows="6"
                placeholder="Write a reply… BBCode supported (b, i, u, url, img, quote, code, list)."
                class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
            ></textarea>

            @error('body')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror

            <div class="mt-3 flex items-center justify-between">
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    Posts are subject to a 10-second flood guard.
                </p>
                <x-ui.button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="submit"
                >
                    <span wire:loading.remove wire:target="submit">Post reply</span>
                    <span wire:loading wire:target="submit">Posting…</span>
                </x-ui.button>
            </div>
        </form>
    @endif
</x-ui.card>
